<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ThemeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'site_ids' => ['required', 'array', 'min:1'],
            'site_ids.*' => ['integer', 'exists:sites,id'],
            'target_version' => ['required', 'string', 'max:100'],
            'environment' => ['required', Rule::in(['stage', 'prod'])],
            'mode' => ['required', Rule::in(['dry_run', 'live'])],
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

            $user = $this->user();
            if (! in_array($user->role, ['owner', 'dev'], true)) {
                $validator->errors()->add('mode', 'Live theme update is restricted to owner/dev.');
            }
        });

        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $env = (string) $this->input('environment');
            $ids = $this->input('site_ids', []);
            if (! is_array($ids)) {
                return;
            }

            foreach ($ids as $id) {
                $site = Site::query()->find((int) $id);
                if (! $site) {
                    continue;
                }
                if ($env === 'stage' && $site->status !== Site::STATUS_STAGE) {
                    $validator->errors()->add('site_ids', 'Site #'.$site->id.' is not a stage site.');
                }
                if ($env === 'prod' && $site->status !== 'active') {
                    $validator->errors()->add('site_ids', 'Site #'.$site->id.' is not an active production site.');
                }
            }
        });
    }
}
