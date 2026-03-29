<?php

declare(strict_types=1);

namespace Moffhub\SmsHandler\Services;

use InvalidArgumentException;
use Moffhub\SmsHandler\Exceptions\InvalidMessageException;

class TemplateService
{
    /**
     * Render a named template with variable interpolation.
     *
     * @param  string  $templateName  The template name from config
     * @param  array<string, string>  $variables  Variables to interpolate
     * @return string The rendered message
     *
     * @throws InvalidArgumentException If template not found
     * @throws InvalidMessageException If rendered message exceeds max length
     */
    public function render(string $templateName, array $variables = []): string
    {
        $templates = config('sms.templates', []);

        if (! isset($templates[$templateName])) {
            throw new InvalidArgumentException("SMS template '{$templateName}' not found in configuration.");
        }

        $template = $templates[$templateName];
        $body = is_array($template) ? ($template['body'] ?? '') : (string) $template;

        $rendered = $this->interpolate($body, $variables);

        $this->validateRenderedLength($rendered);

        return $rendered;
    }

    /**
     * Interpolate variables into a template string using {{ variable }} syntax.
     *
     * @param  string  $template  The template string
     * @param  array<string, string>  $variables  Variables to interpolate
     */
    public function interpolate(string $template, array $variables): string
    {
        $result = $template;

        foreach ($variables as $key => $value) {
            $result = str_replace(
                ["{{{$key}}}", "{{ {$key} }}"],
                (string) $value,
                $result,
            );
        }

        return $result;
    }

    /**
     * Get all configured template names.
     *
     * @return array<string>
     */
    public function getTemplateNames(): array
    {
        return array_keys(config('sms.templates', []));
    }

    /**
     * Check if a template exists.
     */
    public function exists(string $templateName): bool
    {
        return isset(config('sms.templates', [])[$templateName]);
    }

    /**
     * Validate that a rendered message does not exceed the max SMS length.
     *
     * @throws InvalidMessageException
     */
    protected function validateRenderedLength(string $message): void
    {
        $maxLength = (int) config('sms.max_message_length', 918);

        if (mb_strlen($message) > $maxLength) {
            throw InvalidMessageException::tooLong(mb_strlen($message), $maxLength);
        }
    }
}
