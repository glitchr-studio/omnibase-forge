<?php

namespace Base\Forge\Model;

use Symfony\Component\Validator\Constraints as Assert;

/** What a piece of software sends to activate - or re-activate - its machine. */
final class ActivationRequest
{
    public function __construct(
        /** The seat's licence key: glk_… */
        #[Assert\NotBlank]
        public readonly string $key = '',
        /** The software's slug on the forge: a key only opens its own software. */
        #[Assert\NotBlank, Assert\Length(max: 128)]
        public readonly string $software = '',
        /** A stable identifier of the machine (Unity: SystemInfo.deviceUniqueIdentifier; macOS: IOPlatformUUID). Hashed, never stored as is. */
        #[Assert\NotBlank, Assert\Length(min: 8, max: 255)]
        public readonly string $machine = '',
        /** What the machine is called, to recognise it by: "MacBook Air de Camille". */
        #[Assert\Length(max: 128)]
        public readonly ?string $name = null,
        /** macos, windows, linux, android, ios... */
        #[Assert\Length(max: 32)]
        public readonly ?string $platform = null,
        /** The software's own version. */
        #[Assert\Length(max: 32)]
        public readonly ?string $version = null,
    ) {
    }
}
