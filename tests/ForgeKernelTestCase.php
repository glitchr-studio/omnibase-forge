<?php

namespace Tests\Base\Forge;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * For the tests that build base-bundle entities (Software and Project are
 * threads, owners are users): those need the kernel booted - base-bundle
 * aliases its entities onto App\Entity\* while it boots. So they run inside
 * a host application (KERNEL_CLASS, App\Kernel by default) and are skipped
 * in a bare checkout.
 */
abstract class ForgeKernelTestCase extends KernelTestCase
{
    protected function setUp(): void
    {
        $_SERVER['KERNEL_CLASS'] ??= $_ENV['KERNEL_CLASS'] ?? 'App\\Kernel';
        if (!class_exists($_SERVER['KERNEL_CLASS'])) {
            self::markTestSkipped('Needs a host application kernel (base-bundle entities).');
        }

        self::bootKernel();
    }

    /**
     * A user as base-bundle types them: its entities (Thread's owners)
     * expect the application's App\Entity\User, the aliased class.
     */
    protected function user(): \Base\Entity\User
    {
        return $this->createStub(class_exists('App\\Entity\\User') ? 'App\\Entity\\User' : \Base\Entity\User::class);
    }
}
