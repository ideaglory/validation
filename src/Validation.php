<?php

namespace Ideaglory;

/**
 * Custom Request Validation and Data Handling
 * 
 * A lightweight PHP class for validating various data types and values, 
 * including emails, numbers, and strings. This class provides a simple 
 * way to validate input and ensure data correctness in PHP applications.
 *
 * Author: Ideaglory
 * GitHub: https://github.com/ideaglory/validation
 * 
 */

class Validation
{
    private  $data = [];          // Holds the request data
    private  $errors = [];        // Holds validation errors
    private  $rules = [];         // Validation rules
    private  $messages = [];      // Custom error messages
    private  $customRules = [];   // Custom validation rules
    private  $defaults = [];      // Default values for fields

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Define validation rules for the request.
     *
     * @param array $rules
     * @return void
     */
    public function setRules(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * Define custom error messages for the request.
     *
     * @param array $messages
     * @return void
     */
    public function setMessages(array $messages)
    {
        $this->messages = $messages;
    }

    /**
     * Register a custom validation rule.
     *
     * @param string $ruleName
     * @param callable $callback
     * @return void
     */
    public function addCustomRule(string $ruleName, callable $callback)
    {
        $this->customRules[$ruleName] = $callback;
    }

    /**
     * Set default values for fields if they are missing.
     *
     * @param array $defaults
     * @return void
     */
    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults;

        foreach ($this->defaults as $field => $default) {
            if (!isset($this->data[$field])) {
                $this->data[$field] = $default;
            }
        }
    }

    /**
     * Validate the request data.
     *
     * @return bool
     */
    public function validate(): bool
    {
        foreach ($this->rules as $field => $rules) {
            $value = $this->getValueByPath($field, $this->data);

            foreach (explode('|', $rules) as $rule) {
                if (strpos($rule, ':')) {
                    [$ruleName, $ruleParam] = explode(':', $rule);
                    $this->applyRule($field, $ruleName, $value, $ruleParam);
                } else {
                    $this->applyRule($field, $rule, $value);
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Apply a single validation rule.
     *
     * @param string $field
     * @param string $rule
     * @param mixed $value
     * @param mixed|null $param
     * @return void
     */
    private function applyRule(string $field, string $rule, $value, $param = null)
    {
        // Handle custom rules
        if (isset($this->customRules[$rule])) {
            $isValid = call_user_func($this->customRules[$rule], $value, $param);

            if (!$isValid) {
                $this->addError($field, $this->getErrorMessage($field, $rule, "$field validation failed."));
            }
            return;
        }

        // Handle built-in rules
        switch ($rule) {
            case 'required':
                if ($value === null || trim($value) === "") {
                    $this->addError($field, $this->getErrorMessage($field, $rule, "$field is required."));
                }
                break;
            case 'string':
                if (!is_string($value)) {
                    $this->addError($field, $this->getErrorMessage($field, $rule, "$field must be a string."));
                }
                break;
            case 'integer':
                if (filter_var(trim($value), FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, $this->getErrorMessage($field, $rule, "$field must be an integer."));
                }
                break;
            case 'min':
                if (is_numeric($value)) {
                    if ($value < (int) $param) {
                        $this->addError($field, $this->getErrorMessage($field, $rule, "$field must be at least $param."));
                    }
                } elseif (is_string($value)) {
                    if (strlen($value) < (int) $param) {
                        $this->addError($field, $this->getErrorMessage($field, $rule, "$field must be at least $param characters."));
                    }
                }
                break;
            case 'max':
                if (is_numeric($value)) {
                    if ($value > (int) $param) {
                        $this->addError($field, $this->getErrorMessage($field, $rule, "$field must not exceed $param."));
                    }
                } elseif (is_string($value)) {
                    if (strlen($value) > (int) $param) {
                        $this->addError($field, $this->getErrorMessage($field, $rule, "$field must not exceed $param characters."));
                    }
                }
                break;
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, $this->getErrorMessage($field, $rule, "$field must be a valid email."));
                }
                break;
            case 'boolean':
                if (!is_bool($value)) {
                    $this->addError($field, $this->getErrorMessage($field, $rule, "$field must be a boolean value."));
                }
                break;
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, $this->getErrorMessage($field, $rule, "$field must be a valid URL."));
                }
                break;
            case 'alpha':
                if (!preg_match('/^[a-zA-Z]+$/', $value)) {
                    $this->addError($field, $this->getErrorMessage($field, $rule, "$field must contain only alphabetic characters."));
                }
                break;
            case 'alpha_dash':
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
                    $this->addError($field, $this->getErrorMessage($field, $rule, "$field must contain only alphanumeric characters, dashes, and underscores."));
                }
                break;
            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, $this->getErrorMessage($field, $rule, "$field must be numeric."));
                }
                break;
            case 'equal':
                $compareFieldValue = $this->getValueByPath($param, $this->data);
                if ($value !== $compareFieldValue) {
                    $this->addError($field, $this->getErrorMessage($field, $rule, "$field must be equal to $param."));
                }
                break;
            case 'in':
                if (!in_array($value, explode(',', $param))) {
                    $this->addError($field, $this->getErrorMessage($field, $rule, "$field must be one of the following values: $param."));
                }
                break;
            case 'not_in':
                if (in_array($value, explode(',', $param))) {
                    $this->addError($field, $this->getErrorMessage($field, $rule, "$field must not be one of the following values: $param."));
                }
                break;
            case 'date':
                $date = date_create($value);
                if (!$date || $date->format('Y-m-d') !== $value) {
                    $this->addError($field, $this->getErrorMessage($field, $rule, "$field must be a valid date."));
                }
                break;
            default:
                $this->addError($field, $this->getErrorMessage($field, $rule, "Invalid rule: $rule."));
        }
    }

    /**
     * Get the error message for a validation rule.
     *
     * @param string $field
     * @param string $rule
     * @param string $default
     * @return string
     */
    private function getErrorMessage(string $field, string $rule, string $default): string
    {
        return $this->messages["$field.$rule"] ?? $default;
    }

    /**
     * Add an error to the errors array.
     *
     * @param string $field
     * @param string $message
     * @return void
     */
    private function addError(string $field, string $message)
    {
        $this->errors[$field][] = $message;
    }

    /**
     * Get validation errors.
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the sanitized data.
     *
     * @return array
     */
    public function sanitized(): array
    {
        return $this->sanitize($this->data);
    }

    /**
     * Sanitize the input data.
     *
     * @param array $data
     * @return array
     */
    private function sanitize(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            }
            return $value;
        }, $data);
    }

    /**
     * Get a value from nested data using dot notation.
     *
     * @param string $path
     * @param array $data
     * @return mixed|null
     */
    private function getValueByPath(string $path, array $data)
    {
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                return null;
            }
            $data = $data[$key];
        }
        return $data;
    }
}
