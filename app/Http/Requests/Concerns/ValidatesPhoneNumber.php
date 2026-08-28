<?php

namespace App\Http\Requests\Concerns;

use App\Rules\MyanmarPhoneNumber;
use App\Support\PhoneNumber;

trait ValidatesPhoneNumber
{
    /**
     * @return list<mixed>
     */
    protected function phoneRules(): array
    {
        return ['required', 'string', new MyanmarPhoneNumber];
    }

    /**
     * @return array<string, string>
     */
    protected function phoneMessages(): array
    {
        return [
            'phone.required' => PhoneNumber::REQUIRED_MESSAGE,
        ];
    }
}
