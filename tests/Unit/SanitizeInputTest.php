<?php

namespace Tests\Unit;

use App\Http\Middleware\SanitizeInput;
use Illuminate\Http\Request;
use Tests\TestCase;

class SanitizeInputTest extends TestCase
{
    /**
     * Test that SanitizeInput middleware strips tags from general input values.
     */
    public function test_it_strips_html_tags_from_general_inputs()
    {
        $middleware = new SanitizeInput();
        $request = new Request();
        $request->merge([
            'subject' => '<b>Urgent Help Needed</b>',
            'category' => '<script>alert("hack")</script>listings',
            'description' => 'I need assistance with my listings. <a href="http://malicious-site.com">Click here</a>'
        ]);

        $middleware->handle($request, function ($req) {
            $this->assertEquals('Urgent Help Needed', $req->input('subject'));
            $this->assertEquals('listings', $req->input('category'));
            $this->assertEquals('I need assistance with my listings. Click here', $req->input('description'));
            return response()->json([]);
        });
    }

    /**
     * Test that SanitizeInput middleware strips only scripts from rich-text fields.
     */
    public function test_it_keeps_safe_formatting_tags_in_rich_text_fields()
    {
        $middleware = new SanitizeInput();
        $request = new Request();
        $request->merge([
            'content' => '<p>This is a <strong>valid</strong> paragraph.</p><script>alert("run malicious script")</script>',
            'body' => '<div>Formatted text</div><script src="http://xss.js"></script>'
        ]);

        $middleware->handle($request, function ($req) {
            $this->assertEquals('<p>This is a <strong>valid</strong> paragraph.</p>', $req->input('content'));
            $this->assertEquals('<div>Formatted text</div>', $req->input('body'));
            return response()->json([]);
        });
    }
}
