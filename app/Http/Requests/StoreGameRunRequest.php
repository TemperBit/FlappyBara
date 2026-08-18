<?php

namespace App\Http\Requests;

use App\Models\RaceRoom;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGameRunRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'min:0', 'max:100000'],
            'durationMilliseconds' => ['required', 'integer', 'min:100', 'max:86400000'],
            'raceCode' => [
                'nullable',
                'string',
                'size:6',
                Rule::exists(RaceRoom::class, 'code'),
            ],
        ];
    }

    /**
     * Configure validation that depends on multiple run fields.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $durationMilliseconds = $this->integer('durationMilliseconds');
                $maximumPlausibleScore = intdiv($durationMilliseconds, 700) + 4;

                if ($this->integer('score') > $maximumPlausibleScore) {
                    $validator->errors()->add('score', 'That score is not possible for the submitted run time.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('raceCode')) {
            $this->merge([
                'raceCode' => Str::upper($this->string('raceCode')),
            ]);
        }
    }
}
