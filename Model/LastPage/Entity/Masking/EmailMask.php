<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Masking;

use Mbissonho\RememberAdminLastPage\Api\MaskingStrategyInterface;

/**
 * E-mail-aware masking: interleave-masks the local part and the host, but keeps
 * the structural "@" and the top-level domain intact, so
 * "john.doe@example.com" -> "j•h•.•o•@e•a•p•e.com". The result stays
 * recognisable as an address without disclosing it.
 */
class EmailMask implements MaskingStrategyInterface
{
    private MaskingStrategyInterface $interleaved;

    public function __construct(MaskingStrategyInterface $interleaved)
    {
        $this->interleaved = $interleaved;
    }

    public function mask(string $value): string
    {
        if ($value === '' || strpos($value, '@') === false) {
            return $this->interleaved->mask($value);
        }

        [$local, $domain] = explode('@', $value, 2);

        $dot = strrpos($domain, '.');

        if ($dot === false) {
            return $this->interleaved->mask($local) . '@' . $this->interleaved->mask($domain);
        }

        $host = substr($domain, 0, $dot);
        $tld = substr($domain, $dot);

        return $this->interleaved->mask($local) . '@' . $this->interleaved->mask($host) . $tld;
    }
}
