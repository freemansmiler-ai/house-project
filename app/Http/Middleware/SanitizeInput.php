<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        // Recursively sanitize all input strings
        array_walk_recursive($input, function (&$value, $key) {
            if (is_string($value)) {
                // Remove script tags and their contents completely first
                $value = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $value);

                // Skip specific keys that are allowed to have rich text (like blog posts)
                if (!in_array($key, ['content', 'body'])) {
                    // Completely strip HTML tags for all standard string fields
                    $value = strip_tags($value);
                }
                
                // Trim whitespace
                $value = trim($value);
            }
        });

        // Replace the request input with the sanitized values
        $request->merge($input);

        return $next($request);
    }
}
