<?php

use App\Migration\HtmlToText;

/**
 * The converter is framework-free, so it is exercised directly here.
 */
function htmlToText(?string $html): string
{
    return (new HtmlToText)->convert($html);
}

test('it returns an empty string for empty input', function () {
    expect(htmlToText(null))->toBe('')
        ->and(htmlToText(''))->toBe('')
        ->and(htmlToText("  \n\t "))->toBe('');
});

test('it strips every tag from the output', function () {
    $text = htmlToText('<div class="elementor"><p>Hello <strong>world</strong></p></div>');

    expect($text)->toBe('Hello world')
        ->and($text)->not->toContain('<')
        ->and($text)->not->toContain('elementor');
});

test('it removes scripts, styles and inline media', function () {
    $text = htmlToText(
        '<p>Body</p><script>alert(1)</script><style>.a{color:red}</style>'
        .'<iframe src="https://example.test/embed"></iframe><noscript>off</noscript>'
    );

    expect($text)->toBe('Body');
});

test('it decodes html entities', function () {
    $text = htmlToText('<p>Pasture &amp; fodder &#8211; good &#8216;stuff&#8217;</p>');

    expect($text)->toBe('Pasture & fodder – good ‘stuff’');
});

test('it decodes non-breaking spaces to ordinary spaces', function () {
    $text = htmlToText('<p>Gwalfade&nbsp;LGA</p>');

    expect($text)->toBe('Gwalfade LGA')
        ->and($text)->not->toContain("\u{00A0}");
});

test('it separates block paragraphs with one blank line', function () {
    expect(htmlToText('<p>First</p><p>Second</p><div>Third</div>'))
        ->toBe("First\n\nSecond\n\nThird");
});

test('it keeps headings as their own readable paragraph', function () {
    $text = htmlToText('<h2>Trains 67 Farmers</h2><p>Body text.</p>');

    expect($text)->toBe("Trains 67 Farmers\n\nBody text.")
        ->and($text)->not->toContain('##');
});

test('it preserves unordered list structure', function () {
    expect(htmlToText('<ul><li>Feed</li><li>Fodder</li></ul>'))->toBe("• Feed\n• Fodder");
});

test('it preserves ordered list numbering', function () {
    expect(htmlToText('<ol><li>Plan</li><li>Build</li><li>Hand over</li></ol>'))
        ->toBe("1. Plan\n2. Build\n3. Hand over");
});

test('it turns br into a line break without a blank line', function () {
    expect(htmlToText('<p>Line one<br>Line two</p>'))->toBe("Line one\nLine two");
});

test('it drops images and never emits their urls', function () {
    $text = htmlToText(
        '<p>Before</p><img src="https://example.test/wp-content/uploads/2025/04/photo-1024x682.jpg" alt="A photo"><p>After</p>'
    );

    expect($text)->toBe("Before\n\nAfter")
        ->and($text)->not->toContain('photo-1024x682')
        ->and($text)->not->toContain('wp-content');
});

test('it keeps link text without leaking the href or markup', function () {
    $text = htmlToText('<p>See <a href="https://example.test/gallery">the gallery</a>.</p>');

    expect($text)->toBe('See the gallery.')
        ->and($text)->not->toContain('href')
        ->and($text)->not->toContain('https://');
});

test('it keeps table cells distinguishable within a row', function () {
    expect(htmlToText('<table><tr><td>Dairy</td><td>818</td></tr></table>'))->toBe('Dairy | 818');
});

test('it collapses excessive blank lines', function () {
    $text = htmlToText("<p>One</p>\n\n\n\n<div></div><p>Two</p>");

    expect($text)->toBe("One\n\nTwo")
        ->and($text)->not->toContain("\n\n\n");
});

test('it tolerates malformed html', function () {
    $text = htmlToText('<p>Unclosed <b>bold <p>second paragraph');

    expect($text)->toContain('Unclosed')
        ->and($text)->toContain('bold')
        ->and($text)->toContain('second paragraph')
        ->and($text)->not->toContain('<');
});

test('it leaves plain text untouched', function () {
    $plain = 'A paragraph with L-PRES and 1,305 images.';

    expect(htmlToText($plain))->toBe($plain);
});

test('it handles html without any block elements', function () {
    expect(htmlToText('Just <em>inline</em> text'))->toBe('Just inline text');
});
