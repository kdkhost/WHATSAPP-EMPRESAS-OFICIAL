<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sanitizacao global de inputs - protege contra XSS armazenado,
 * null bytes, unicode exploits.
 *
 * Campos rich-text (Summernote) sao sanitizados de forma branda:
 * remove scripts/event-handlers mas permite HTML seguro.
 */
class SanitizeInput
{
    protected array $richTextFields = [
        'content_html', 'body', 'html', 'message', 'description',
        'content', 'contract_html', 'template_html', 'description_html',
        'terms', 'clauses',
    ];
    protected string $allowedTags = '<p><br><strong><b><em><i><u><s><ul><ol><li><a><h1><h2><h3><h4><h5><h6><blockquote><pre><code><hr><table><thead><tbody><tr><th><td><img><span><div><colgroup><col>';

    protected array $htmlAllowedRoutes = [
        'admin.contracts.*',
        'contracts.*',
        'admin.templates.*',
        'admin.invoice-editor.*',
        'painel.contracts.*',
        'tenant.contracts.*',
    ];

    protected array $htmlAllowedFields = [
        'content', 'body', 'html', 'contract_html', 'template_html',
        'description_html', 'terms', 'clauses', 'content_html',
    ];

    protected array $skipSanitizeFields = [
        'api_key', 'security_entry', 'api_secret', 'token', 'password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->hasFile('file')) {
            return $next($request);
        }

        $input = $request->all();
        $sanitized = $this->sanitizeArray($input, $request);
        $request->merge($sanitized);

        return $next($request);
    }

    protected function isHtmlAllowedRoute(Request $request): bool
    {
        foreach ($this->htmlAllowedRoutes as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function sanitizeArray(array $data, Request $request): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value, $request);
            } elseif (is_string($value)) {
                $data[$key] = $this->sanitizeValue($value, $key, $request);
            }
        }
        return $data;
    }

    protected function sanitizeValue(string $value, string $key, Request $request): string
    {
        $value = str_replace(["\0", "\x00"], '', $value);
        $value = str_replace("\r\n", "\n", $value);

        if (in_array($key, $this->skipSanitizeFields, true)) {
            return $value;
        }

        if (in_array($key, $this->richTextFields, true)) {
            return $this->sanitizeRichText($value);
        }

        if ($this->isHtmlAllowedRoute($request) && in_array($key, $this->htmlAllowedFields, true)) {
            return $this->sanitizeRichHtml($value);
        }

        return trim(strip_tags($value));
    }

    protected function sanitizeRichText(string $html): string
    {
        $html = preg_replace('/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is', '', $html);
        $html = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\bon\w+\s*=\s*[^\s>]*/i', '', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);
        $html = preg_replace('/vbscript\s*:/i', '', $html);
        $html = preg_replace('/data\s*:[^;]+;base64/i', 'blocked:', $html);
        $html = preg_replace('/<\s*(iframe|object|embed|form|input|button|textarea|select)[^>]*\/?>/i', '', $html);
        $html = strip_tags($html, $this->allowedTags);
        return $html;
    }

    protected function sanitizeRichHtml(string $value): string
    {
        $value = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $value);
        $value = preg_replace('/\son\w+="[^"]*"/i', '', $value);
        $value = preg_replace("/\son\w+='[^']*'/i", '', $value);
        $value = preg_replace('/javascript\s*:/i', '', $value);
        return $value;
    }
}
