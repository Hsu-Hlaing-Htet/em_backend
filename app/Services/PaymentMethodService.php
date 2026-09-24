<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Services\Concerns\AppliesListQuery;
use App\Support\AdminListSorts;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentMethodService
{
    use AppliesListQuery;

    public const QR_DIRECTORY = 'payment-method-qrs';

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = PaymentMethod::query();
        $this->applyStatusFilter($query, $params);
        $this->applyListQuery(
            $query,
            $params,
            ['name', 'slug', 'status', 'type'],
            array_merge(AdminListSorts::namedSettings(), [
                'type' => 'type',
                'sort_order' => 'sort_order',
            ]),
            static function ($builder): void {
                $builder
                    ->orderBy('payment_methods.sort_order')
                    ->orderBy('payment_methods.name')
                    ->orderBy('payment_methods.id');
            }
        );

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): PaymentMethod
    {
        return PaymentMethod::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PaymentMethod
    {
        $prepared = $this->prepareData($data);

        if (! array_key_exists('sort_order', $prepared) || $prepared['sort_order'] === null || $prepared['sort_order'] === '') {
            $prepared['sort_order'] = $this->nextSortOrder();
        }

        return PaymentMethod::query()->create($prepared);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $prepared = $this->prepareData($data, $paymentMethod);

        // Ordering is managed automatically / by seeders — ignore client-provided values on update
        // unless an explicit sort_order key is present (API/tests). Prefer preserving existing.
        if (! array_key_exists('sort_order', $data)) {
            unset($prepared['sort_order']);
        }

        $paymentMethod->update($prepared);

        return $paymentMethod->fresh();
    }

    public function delete(PaymentMethod $paymentMethod): void
    {
        $this->deleteQrImage($paymentMethod->qr_image_path);
        $paymentMethod->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customerAvailableMethods(): array
    {
        return PaymentMethod::query()
            ->availableForCustomer()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PaymentMethod $method) => [
                'id' => $method->id,
                'name' => $method->name,
                'type' => $method->type,
                'account_name' => $method->account_name,
                'account_number' => $method->account_number,
                'phone_number' => $method->phone_number,
                'qr_image_url' => $method->qrImageUrl(),
                'instructions' => $method->instructions,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data, ?PaymentMethod $existing = null): array
    {
        unset($data['slug'], $data['qr_image_url']);

        if (! empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        if (array_key_exists('is_customer_visible', $data)) {
            // Admin UI no longer controls this flag; ignore client-provided values.
            unset($data['is_customer_visible']);
        }

        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null && $data['sort_order'] !== '') {
            $data['sort_order'] = (int) $data['sort_order'];
        }

        $removeQr = false;
        if (array_key_exists('remove_qr_image', $data)) {
            $removeQr = filter_var($data['remove_qr_image'], FILTER_VALIDATE_BOOLEAN);
            unset($data['remove_qr_image']);
        }

        $uploadedQr = $data['qr_image'] ?? null;
        unset($data['qr_image']);

        if ($uploadedQr instanceof UploadedFile) {
            $previousPath = $existing?->qr_image_path;
            $data['qr_image_path'] = $uploadedQr->store(self::QR_DIRECTORY, 'public');

            if ($previousPath && $previousPath !== $data['qr_image_path']) {
                $this->deleteQrImage($previousPath);
            }
        } elseif ($removeQr) {
            if ($existing?->qr_image_path) {
                $this->deleteQrImage($existing->qr_image_path);
            }
            $data['qr_image_path'] = null;
        }

        // Normalize type-specific fields so stale values are never persisted.
        $type = $data['type'] ?? $existing?->type;
        $status = $data['status'] ?? $existing?->status;
        $typeChanged = $existing !== null
            && array_key_exists('type', $data)
            && (string) $data['type'] !== (string) $existing->type;

        if ($type === PaymentMethod::TYPE_WALLET) {
            $data['account_name'] = null;
            $data['account_number'] = null;

            if (array_key_exists('phone_number', $data) && $data['phone_number'] !== null && $data['phone_number'] !== '') {
                $normalizedPhone = PhoneNumber::normalizePaymentMethodWalletPhone($data['phone_number']);
                if ($normalizedPhone !== null) {
                    $data['phone_number'] = $normalizedPhone;
                }
            }
        } elseif ($type === PaymentMethod::TYPE_BANK_TRANSFER) {
            $data['phone_number'] = null;
        } else {
            $data['phone_number'] = null;
            $data['account_name'] = null;
            $data['account_number'] = null;
        }

        // Derive legacy column from Active + non-Cash (single source of truth).
        $data['is_customer_visible'] = PaymentMethod::syncCustomerVisibleFlag(
            is_string($type) ? $type : null,
            is_string($status) ? $status : null,
        );

        // QR images are wallet-only. Clear when creating/changing away from wallet.
        if ($type !== PaymentMethod::TYPE_WALLET && ! ($uploadedQr instanceof UploadedFile)) {
            if ($existing === null || $typeChanged || $removeQr) {
                if ($existing?->qr_image_path) {
                    $this->deleteQrImage($existing->qr_image_path);
                }
                $data['qr_image_path'] = null;
            }
        }

        return $data;
    }

    /**
     * Place new methods after existing ones (seeders use steps of 10).
     */
    private function nextSortOrder(): int
    {
        $max = (int) PaymentMethod::query()->max('sort_order');

        return $max + 10;
    }

    private function deleteQrImage(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
