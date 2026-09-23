<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Services\Concerns\AppliesListQuery;
use App\Support\AdminListSorts;
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
            ])
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
        return PaymentMethod::query()->create($this->prepareData($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $prepared = $this->prepareData($data, $paymentMethod);
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
            $data['is_customer_visible'] = filter_var(
                $data['is_customer_visible'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? false;
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

        // Cash / non-wallet methods should not keep empty wallet-only fields forced.
        if (($data['type'] ?? $existing?->type) === PaymentMethod::TYPE_CASH) {
            $data['phone_number'] = $data['phone_number'] ?? null;
            $data['account_name'] = $data['account_name'] ?? null;
            $data['account_number'] = $data['account_number'] ?? null;
            if (! ($uploadedQr instanceof UploadedFile) && ! $removeQr) {
                // Keep existing QR path unless explicitly cleared — but Cash shouldn't have QR.
            }
        }

        return $data;
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
