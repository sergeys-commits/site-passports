<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoteToProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'site_id' => [
                'required',
                'integer',
                Rule::exists('sites', 'id')->where('status', Site::STATUS_STAGE),
            ],
            'stage_domain' => ['required', 'string', 'max:190'],
            'prod_domain' => ['required', 'string', 'max:190', 'regex:/^[a-z0-9.\-]+$/'],
            'mode' => ['required', Rule::in(['dry_run', 'live'])],
            'confirm_phrase' => ['nullable', 'string', 'max:190', 'required_if:mode,live'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (($this->input('mode') ?? '') !== 'live') {
                return;
            }

            if ((string) $this->input('confirm_phrase') !== (string) $this->input('prod_domain')) {
                $validator->errors()->add(
                    'confirm_phrase',
                    'Confirmation phrase must match the production domain exactly.',
                );
            }
        });

        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $siteId = (int) $this->input('site_id');
            $stageDomain = (string) $this->input('stage_domain');
            $site = Site::query()->find($siteId);
            if ($site && $site->stage_domain !== $stageDomain) {
                $validator->errors()->add('stage_domain', 'Stage domain does not match the selected site.');
            }
        });
    }
}
