<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity;

use Magento\Framework\Encryption\EncryptorInterface;
use Mbissonho\RememberAdminLastPage\Api\Data\EntityContextInterface;
use Mbissonho\RememberAdminLastPage\Api\Data\EntityContextInterfaceFactory;

/**
 * Seals a {@see EntityContextInterface} into an opaque token for the browser and
 * opens it again server-side.
 *
 * Why a token and not the raw {type, id}
 * --------------------------------------
 * The remembered context lives in the deslogged tab's sessionStorage — the only
 * client store that survives the logout and is present at resume time. So it
 * necessarily reaches the client. The token is the installation-keyed
 * encryption of `type:id`, which buys two things:
 *   - confidentiality: a reader of sessionStorage (shared machine, XSS) learns
 *     neither which entity nor even its type;
 *   - integrity/authenticity: the (type,id) pair is bound and tamper-evident, so
 *     the preview endpoint cannot be driven with a forged {type,id} to probe for
 *     arbitrary models. It only ever resolves a reference the server itself
 *     minted for a page that was actually visited.
 * Decryption is server-only; any failure simply yields "no details".
 */
class EntityTokenizer
{
    private const SEPARATOR = ':';

    /** Entity type codes are pool keys: a single word segment. */
    private const TYPE_PATTERN = '#^[a-z0-9_]+$#i';

    /** Ids we accept back: word/dash characters, never the separator. */
    private const ID_PATTERN = '#^[a-z0-9_\-]+$#i';

    private EncryptorInterface $encryptor;

    private EntityContextInterfaceFactory $contextFactory;

    public function __construct(
        EncryptorInterface $encryptor,
        EntityContextInterfaceFactory $contextFactory
    ) {
        $this->encryptor = $encryptor;
        $this->contextFactory = $contextFactory;
    }

    public function tokenize(EntityContextInterface $context): string
    {
        return $this->encryptor->encrypt(
            $context->getEntityTypeCode() . self::SEPARATOR . $context->getEntityId()
        );
    }

    public function detokenize(string $token): ?EntityContextInterface
    {
        if ($token === '') {
            return null;
        }

        try {
            $plain = $this->encryptor->decrypt($token);
        } catch (\Exception $e) {
            return null;
        }

        if ($plain === '' || substr_count($plain, self::SEPARATOR) < 1) {
            return null;
        }

        [$type, $id] = explode(self::SEPARATOR, $plain, 2);

        if (!preg_match(self::TYPE_PATTERN, $type) || !preg_match(self::ID_PATTERN, $id)) {
            return null;
        }

        return $this->contextFactory->create([
            'entityTypeCode' => $type,
            'entityId' => $id,
        ]);
    }
}
