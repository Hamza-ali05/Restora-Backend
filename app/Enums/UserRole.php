<?php

namespace App\Enums;

enum UserRole: string
{
    case PlatformSuperAdmin = 'platform_super_admin';
    case TenantOwner = 'tenant_owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Waiter = 'waiter';
    case Kitchen = 'kitchen';
    case Customer = 'customer';

    public function isPlatformRole(): bool
    {
        return $this === self::PlatformSuperAdmin;
    }

    public function isTenantRole(): bool
    {
        return ! $this->isPlatformRole();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
