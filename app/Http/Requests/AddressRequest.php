<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'country'         => ['required', 'in:EG,SA'],
            'label'           => ['required', 'string', 'max:190', Rule::unique("addresses", "label")->ignore($this->route('address.id'))->where('user_id', auth()->user()->id)],
            'governorate'     => ['required', 'string', 'max:100'],
            'city'            => ['nullable', 'string', 'max:100'],
            'street'          => ['nullable', 'string', 'max:200'],
            'building_number' => ['nullable', 'string', 'max:50'],
            'apartment'       => ['nullable', 'string', 'max:200'],
            'phone'           => ['required', 'string', function ($attribute, $value, $fail) {
                $country = $this->input('country');
                if ($country === 'EG') {
                    // مصر: 11 رقم يبدأ بـ 01
                    if (!preg_match('/^01[0-9]{9}$/', $value)) {
                        $fail('رقم الهاتف المصري يجب أن يكون 11 رقم ويبدأ بـ 01');
                    }
                } elseif ($country === 'SA') {
                    // السعودية: 9 أرقام يبدأ بـ 5
                    if (!preg_match('/^5[0-9]{8}$/', $value)) {
                        $fail('رقم الهاتف السعودي يجب أن يكون 9 أرقام ويبدأ بـ 5');
                    }
                }
            }],
            'latitude'        => ['nullable', 'max:190'],
            'longitude'       => ['nullable', 'max:190'],
            'address'         => ['nullable', 'string', 'max:500'],
        ];
    }
}
