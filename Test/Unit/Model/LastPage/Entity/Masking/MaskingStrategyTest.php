<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Test\Unit\Model\LastPage\Entity\Masking;

use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Masking\EmailMask;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Masking\InterleavedMask;
use PHPUnit\Framework\TestCase;

class MaskingStrategyTest extends TestCase
{
    public function testInterleavedHidesEveryOtherCharacter(): void
    {
        $mask = new InterleavedMask('•', 0);

        $this->assertSame('M•t•u•', $mask->mask('Mateus'));
    }

    public function testInterleavedPreservesSpaces(): void
    {
        $mask = new InterleavedMask('•', 0);

        // Index-based across the whole string; the space at index 4 is preserved.
        $this->assertSame('J•h• •o•', $mask->mask('John Doe'));
    }

    public function testInterleavedHandlesEmptyString(): void
    {
        $mask = new InterleavedMask('•', 0);

        $this->assertSame('', $mask->mask(''));
    }

    public function testEmailMaskKeepsStructureAndTld(): void
    {
        $mask = new EmailMask(new InterleavedMask('•', 0));

        $this->assertSame('j•h•.•o•@e•a•p•e.com', $mask->mask('john.doe@example.com'));
    }

    public function testEmailMaskFallsBackForNonEmail(): void
    {
        $mask = new EmailMask(new InterleavedMask('•', 0));

        $this->assertSame('M•t•u•', $mask->mask('Mateus'));
    }
}
