<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * IpAddress - Immutable Value Object with GDPR-Compliant Anonymization
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY & COMPLIANCE: Privacy-Safe IP Representation                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  GDPR ARTICLE 4(1): IP addresses are Personal Data                      ║
 * ║    - They can identify individuals directly or indirectly               ║
 * ║    - Long-term storage of full IPs requires legal basis                 ║
 * ║                                                                          ║
 * ║  ANONYMIZATION STRATEGY:                                                ║
 * ║    IPv4: Zero the last octet     (192.168.1.45 → 192.168.1.0)          ║
 * ║    IPv6: Zero the last 80 bits   (2001:db8::1 → 2001:db8::)            ║
 * ║                                                                          ║
 * ║  WHY THIS APPROACH?                                                     ║
 * ║    - Preserves geographical/ISP utility for fraud detection             ║
 * ║    - Destroys individual identification capability                      ║
 * ║    - Consistent with Google Analytics IP anonymization                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   $ip = new IpAddress($request->ip());
 *   $anonymized = $ip->anonymize(); // Returns new instance
 *   $context = new AuditContext(actorId: null, ipAddress: $anonymized, ...);
 */
final readonly class IpAddress
{
    /**
     * Flag indicating if this IP has been anonymized.
     */
    public bool $isAnonymized;

    /**
     * @param  string  $value  The IP address string (IPv4 or IPv6)
     * @param  bool  $isAnonymized  Whether this instance is already anonymized
     *
     * @throws InvalidArgumentException If value is not a valid IP address
     */
    public function __construct(
        public string $value,
        bool $isAnonymized = false
    ) {
        if (! filter_var($value, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException(
                "Invalid IP address format: {$value}"
            );
        }

        $this->isAnonymized = $isAnonymized;
    }

    /**
     * Create from a raw string.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Create an anonymized IP address.
     *
     * ╔══════════════════════════════════════════════════════════════════════════╗
     * ║  GDPR ANONYMIZATION ALGORITHM                                           ║
     * ╠══════════════════════════════════════════════════════════════════════════╣
     * ║                                                                          ║
     * ║  IPv4 HANDLING (32-bit address):                                        ║
     * ║    Original: 192.168.1.45 → Split into octets [192, 168, 1, 45]         ║
     * ║    Mask:     Set last octet to 0 → [192, 168, 1, 0]                     ║
     * ║    Result:   192.168.1.0                                                ║
     * ║                                                                          ║
     * ║    RATIONALE: The last octet identifies the host within a /24 subnet.  ║
     * ║    Zeroing it preserves the network (ISP/organization) but removes     ║
     * ║    individual host identification. ~256 hosts share the same value.    ║
     * ║                                                                          ║
     * ║  IPv6 HANDLING (128-bit address):                                       ║
     * ║    Original: 2001:0db8:85a3:0000:0000:8a2e:0370:7334                    ║
     * ║    Mask:     Zero last 80 bits (keep first 48 bits = /48 prefix)        ║
     * ║    Result:   2001:0db8:85a3::                                           ║
     * ║                                                                          ║
     * ║    RATIONALE: /48 prefix is typically assigned to organizations/ISPs.  ║
     * ║    The Interface ID (last 64 bits) often contains MAC address info     ║
     * ║    which is highly identifying. We also zero 16 bits of subnet.        ║
     * ║                                                                          ║
     * ║  IMMUTABILITY: Returns NEW instance, original unchanged.               ║
     * ╚══════════════════════════════════════════════════════════════════════════╝
     *
     * @return self A new IpAddress instance with anonymized value
     */
    public function anonymize(): self
    {
        if ($this->isAnonymized) {
            return $this; // Already anonymized, return same instance
        }

        // Detect IP version
        if (filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->anonymizeIpv4();
        }

        if (filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->anonymizeIpv6();
        }

        // Fallback (should never reach due to constructor validation)
        return new self('0.0.0.0', true);
    }

    /**
     * Anonymize IPv4 address by zeroing the last octet.
     *
     * Algorithm: Split by '.', replace last element with '0', rejoin.
     */
    private function anonymizeIpv4(): self
    {
        $octets = explode('.', $this->value);
        $octets[3] = '0'; // Zero the last octet (host identifier)

        return new self(implode('.', $octets), true);
    }

    /**
     * Anonymize IPv6 address by zeroing the last 80 bits.
     *
     * Algorithm:
     * 1. Expand the IPv6 to full 8 groups (handle :: notation)
     * 2. Keep first 3 groups (48 bits = /48 prefix)
     * 3. Zero remaining groups
     * 4. Compress back to standard notation
     */
    private function anonymizeIpv6(): self
    {
        // Expand the IPv6 address to full 8 groups
        $packed = inet_pton($this->value);

        if ($packed === false) {
            return new self('::', true);
        }

        // Zero the last 10 bytes (80 bits), keeping first 6 bytes (48 bits)
        // This preserves the /48 prefix (typically organization/ISP allocation)
        $masked = substr($packed, 0, 6).str_repeat("\x00", 10);

        $anonymized = inet_ntop($masked);

        if ($anonymized === false) {
            return new self('::', true);
        }

        return new self($anonymized, true);
    }

    /**
     * Check if this is a private/internal IP address.
     *
     * Private ranges: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, fc00::/7
     */
    public function isPrivate(): bool
    {
        return ! filter_var(
            $this->value,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Check if this is an IPv4 address.
     */
    public function isIpv4(): bool
    {
        return (bool) filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    /**
     * Check if this is an IPv6 address.
     */
    public function isIpv6(): bool
    {
        return (bool) filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    /**
     * Strict equality comparison.
     */
    public function equals(IpAddress $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Get the string value.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
