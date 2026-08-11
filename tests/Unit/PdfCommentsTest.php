<?php

namespace Tests\Unit;

use App\Support\PdfComments;
use PHPUnit\Framework\TestCase;

class PdfCommentsTest extends TestCase
{
    public function test_disabled_without_manual_comment_keeps_current_behavior(): void
    {
        $this->assertSame('', PdfComments::combine('', 'Leyenda fija', false));
    }

    public function test_disabled_with_manual_comment_returns_only_manual_comment(): void
    {
        $this->assertSame('Entrega matutina', PdfComments::combine('Entrega matutina', 'Leyenda fija', false));
    }

    public function test_enabled_without_manual_comment_returns_only_forced_comment(): void
    {
        $this->assertSame('Leyenda fija', PdfComments::combine('', 'Leyenda fija', true));
    }

    public function test_enabled_with_manual_comment_appends_forced_comment(): void
    {
        $this->assertSame(
            "Entrega matutina\n\nLeyenda fija",
            PdfComments::combine('Entrega matutina', 'Leyenda fija', true)
        );
    }

    public function test_enabled_with_empty_forced_comment_returns_manual_comment(): void
    {
        $this->assertSame('Entrega matutina', PdfComments::combine('Entrega matutina', " \n ", true));
    }

    public function test_forced_comment_preserves_lines_and_normalizes_windows_line_endings(): void
    {
        $this->assertSame(
            "Manual\n\nLínea uno\nLínea dos",
            PdfComments::combine('Manual', "Línea uno\r\nLínea dos", true)
        );
    }
}
