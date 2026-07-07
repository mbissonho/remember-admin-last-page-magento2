<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Masking;

use Mbissonho\RememberAdminLastPage\Api\MaskingStrategyInterface;

/**
 * Hides every other character, keeping the rest as a recognisability hint:
 * "Mateus" -> "M•t•u•". Spaces are preserved so multi-word values keep their
 * shape. Multibyte safe.
 */
class InterleavedMask implements MaskingStrategyInterface
{
    private string $maskChar;

    private int $keepParity;

    /**
     * @param string $maskChar character shown in place of a hidden one
     * @param int $keepParity 0 keeps characters at even indexes, 1 keeps odd ones
     */
    public function __construct(string $maskChar = '•', int $keepParity = 0)
    {
        $this->maskChar = $maskChar !== '' ? $maskChar : '•';
        $this->keepParity = $keepParity === 1 ? 1 : 0;
    }

    public function mask(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $chars = mb_str_split($value);

        foreach ($chars as $index => $char) {
            if ($char === ' ') {
                continue;
            }

            if ($index % 2 !== $this->keepParity) {
                $chars[$index] = $this->maskChar;
            }
        }

        return implode('', $chars);
    }
}
