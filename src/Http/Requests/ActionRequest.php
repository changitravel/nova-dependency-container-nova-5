<?php

namespace Alexwenzel\DependencyContainer\Http\Requests;

use Alexwenzel\DependencyContainer\DependencyContainer;
use Alexwenzel\DependencyContainer\HasChildFields;
use Laravel\Nova\Http\Requests\ActionRequest as NovaActionRequest;
use Laravel\Nova\Http\Requests\NovaRequest;

class ActionRequest extends NovaActionRequest
{
    use HasChildFields;

    /**
     * Validate fields whose dependencies are satisfied.
     */
    public function validateFields(): array
    {
        $availableFields = [];

        $this->childFieldsArr = [];

        foreach ($this->action()->fields($this) as $field) {
            if ($field instanceof DependencyContainer) {
                if ($field->areDependenciesSatisfied($this)) {
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

        return $this->validate(
            collect($availableFields)
                ->mapWithKeys(function ($field): array {
                    return $field->getCreationRules($this);
                })
                ->all()
        );
    }

    public function novaRequest(): NovaRequest
    {
        return NovaRequest::createFrom($this);
    }
}
