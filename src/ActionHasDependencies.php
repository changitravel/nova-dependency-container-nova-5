<?php

namespace Alexwenzel\DependencyContainer;

use Illuminate\Support\Facades\Validator;
use Laravel\Nova\Http\Requests\ActionRequest;

trait ActionHasDependencies
{
    use HasChildFields;

    /**
     * Return only fields that should participate in validation.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    protected function fieldsForValidation(ActionRequest $request): array
    {
        $availableFields = [];

        // Prevent fields from accumulating if the action instance is reused.
        $this->childFieldsArr = [];

        foreach ($this->fields($request) as $field) {
            if ($field instanceof DependencyContainer) {
                if ($field->areDependenciesSatisfied($request)) {
                    $availableFields[] = $field;

                    $this->extractChildFields(
                        $field->meta['fields']
                    );
                }

                continue;
            }

            $availableFields[] = $field;
        }

        if ($this->childFieldsArr !== []) {
            $availableFields = array_merge(
                $availableFields,
                $this->childFieldsArr
            );
        }

        return $availableFields;
    }

    /**
     * Validate fields whose dependencies are satisfied.
     */
    public function validateFields(ActionRequest $request): array
    {
        $fields = collect(
            $this->fieldsForValidation($request)
        );

        return Validator::make(
            $request->all(),
            $fields->mapWithKeys(function ($field) use ($request): array {
                return $field->getCreationRules($request);
            })->all(),
            [],
            $fields
                ->reject(fn ($field): bool => empty($field->name))
                ->mapWithKeys(fn ($field): array => [
                    $field->attribute => $field->name,
                ])
                ->all()
        )->after(function ($validator) use ($request): void {
            $this->afterValidation($request, $validator);
        })->validate();
    }
}
