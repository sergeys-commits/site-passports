<?php

namespace App\Http\Requests\Deployments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StageProvisionRunRequest extends FormRequest
{
public function authorize(): bool
{
return (bool) $this->user();
}

public function rules(): array
{
return [
'name' => ['required', 'string', 'max:190'],
'domain' => ['nullable', 'string', 'max:190'],
'stage_domain' => ['required', 'string', 'max:190'],
'mode' => ['required', Rule::in(['dry_run', 'live'])],
'confirm_phrase' => ['nullable', 'string', 'required_if:mode,live', 'in:CONFIRM STAGE LIVE'],
'server_host' => ['nullable', 'string', 'max:190'],
'cms' => ['nullable', 'string', 'max:100'],
'template' => ['nullable', 'string', 'max:100'],
'group_id' => ['nullable', 'integer'],
];
}
}
