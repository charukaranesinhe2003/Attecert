<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public function validate(array $data, array $rules): array
    {
        $errors = [];
        $clean = [];
        foreach ($rules as $field => $ruleString) {
            $ruleset = explode('|', $ruleString);
            $value = $data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $required = in_array('required', $ruleset, true);
            $nullable = in_array('nullable', $ruleset, true);
            if (($value === null || $value === '' || $value === []) && !$required) {
                $clean[$field] = ($nullable || $value === null) ? ($value === '' ? null : $value) : $value;
                continue;
            }
            if ($required && ($value === null || $value === '' || $value === [])) {
                $errors[$field] = __('validation.required', ['field' => __('fields.' . $field)]);
                continue;
            }
            $numeric = in_array('numeric', $ruleset, true);
            foreach ($ruleset as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }
                $error = $this->check($field, $value, $rule, $numeric);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break;
                }
            }
            if (!isset($errors[$field])) {
                $clean[$field] = $value;
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $data);
        }

        return $clean;
    }

    private function check(string $field, mixed $value, string $rule, bool $numeric): ?string
    {
        $label = __('fields.' . $field);
        if ($label === 'fields.' . $field) {
            $label = str_replace('_', ' ', $field);
        }
        if ($rule === 'email' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
            return __('validation.email', ['field' => $label]);
        }
        if ($rule === 'numeric' && !is_numeric((string) $value)) {
            return __('validation.numeric', ['field' => $label]);
        }
        if ($rule === 'date') {
            $date = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
            if ($date === false || $date->format('Y-m-d') !== $value) {
                return $label . ' must be a valid date in YYYY-MM-DD format.';
            }
        }
        if (str_starts_with($rule, 'min:')) {
            $min = (float) substr($rule, 4);
            if ($numeric) {
                if ((float) $value < $min) {
                    return __('validation.min', ['field' => $label, 'min' => (string) $min]);
                }
            } elseif (mb_strlen((string) $value) < (int) $min) {
                return __('validation.min_length', ['field' => $label, 'min' => (string) (int) $min]);
            }
        }
        if (str_starts_with($rule, 'max:')) {
            $max = (float) substr($rule, 4);
            if ($numeric) {
                if ((float) $value > $max) {
                    return __('validation.max', ['field' => $label, 'max' => (string) $max]);
                }
            } elseif (mb_strlen((string) $value) > (int) $max) {
                return __('validation.max_length', ['field' => $label, 'max' => (string) (int) $max]);
            }
        }
        if (str_starts_with($rule, 'in:')) {
            $options = explode(',', substr($rule, 3));
            if (!in_array((string) $value, $options, true)) {
                return __('validation.in', ['field' => $label]);
            }
        }

        return null;
    }
}
