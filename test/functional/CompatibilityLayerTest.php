<?php

namespace Lcobucci\JWT\FunctionalTests;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Keys;
use Lcobucci\JWT\Signer\Hmac\Sha256 as HmacSha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\Signature;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function time;

/**
 * @covers \Lcobucci\JWT\Builder
 * @covers \Lcobucci\JWT\Configuration
 * @covers \Lcobucci\JWT\Claim\Factory
 * @covers \Lcobucci\JWT\Claim\Basic
 * @covers \Lcobucci\JWT\Parser
 * @covers \Lcobucci\JWT\Parsing\Encoder
 * @covers \Lcobucci\JWT\Parsing\Decoder
 * @covers \Lcobucci\JWT\Signer\Key
 * @covers \Lcobucci\JWT\Signer\Key\InMemory
 * @covers \Lcobucci\JWT\Signer\Key\LocalFileReference
 * @covers \Lcobucci\JWT\Signer\BaseSigner
 * @covers \Lcobucci\JWT\Signer\OpenSSL
 * @covers \Lcobucci\JWT\Signer\Hmac
 * @covers \Lcobucci\JWT\Signer\Hmac\Sha256
 * @covers \Lcobucci\JWT\Signer\Rsa
 * @covers \Lcobucci\JWT\Signer\Rsa\Sha256
 * @covers \Lcobucci\JWT\Signature
 * @covers \Lcobucci\JWT\Token
 * @covers \Lcobucci\JWT\Token\DataSet
 * @covers \Lcobucci\JWT\Validation\Validator
 * @covers \Lcobucci\JWT\Validation\Constraint\SignedWith
 */
final class CompatibilityLayerTest extends TestCase
{
    use Keys;

    /** @test */
    public function tokenCanBeInstantiatedInTheNewNamespace()
    {
        $token = new Plain(
            new DataSet(['typ' => 'JWT', 'alg' => 'none'], ''),
            new DataSet([], ''),
            Signature::fromEmptyData()
        );

        self::assertSame('JWT', $token->headers()->get('typ'));
    }

    /** @test */
    public function registeredDateClaimsShouldBeConvertedToDateObjects()
    {
        $now = time();

        $config = Configuration::forSymmetricSigner(new HmacSha256(), Key\InMemory::plainText('testing'));

        $token = $config->builder()
            ->issuedAt($now)
            ->permittedFor('test')
            ->canOnlyBeUsedAfter($now + 5)
            ->expiresAt($now + 3600)
            ->getToken($config->signer(), $config->signingKey());

        $expectedNow = new DateTimeImmutable('@' . $now);

        self::assertSame(['test'], $token->claims()->get('aud'));
        self::assertEquals($expectedNow, $token->claims()->get('iat'));
        self::assertEquals($expectedNow->modify('+5 seconds'), $token->claims()->get('nbf'));
        self::assertEquals($expectedNow->modify('+1 hour'), $token->claims()->get('exp'));

        $token2 = $config->parser()->parse($token->toString());

        self::assertSame(['test'], $token2->claims()->get('aud'));
        self::assertEquals($expectedNow, $token2->claims()->get('iat'));
        self::assertEquals($expectedNow->modify('+5 seconds'), $token2->claims()->get('nbf'));
        self::assertEquals($expectedNow->modify('+1 hour'), $token2->claims()->get('exp'));
    }

    /**
     * @test
     *
     * @dataProvider possibleKeys
     *
     * @param Key $key
     */
    public function tokenCanBeBuiltWithNewKeyObjects(Key $key)
    {
        $config = Configuration::forAsymmetricSigner(new Sha256(), $key, self::$rsaKeys['public']);
        $config->setValidationConstraints(new SignedWith($config->signer(), $config->verificationKey()));

        $token = $config->builder()
            ->issuedBy('me')
            ->relatedTo('user123')
            ->getToken($config->signer(), $config->signingKey());

        self::assertTrue($config->validator()->validate($token, ...$config->validationConstraints()));
    }

    public function possibleKeys()
    {
        $rsaKey = <<<'RSA'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDTvwE87MtgREYL
TL4aHhQo3ZzogmxxvMUsKnPzyxRs1YrXOSOpwN0npsXarBKKVIUMNLfFODp/vnQn
2Zp06N8XG59WAOKwvC4MfxLDQkA+JXggzHlkbVoTN+dUkdYIFqSKuAPGwiWToRK2
SxEhij3rE2FON8jQZvDxZkiP9a4vxJO3OTPQwKredXFiObsXD/c3RtLFhKctjCyH
OIrP0bQEsee/m7JNtG4ry6BPusN6wb+vJo5ieBYPa3c19akNq6q/nYWhplhkkJSu
aOrL5xXEFzI5TvcvnXR568GVcxK8YLfFkdxpsXGt5rAbeh0h/U5kILEAqv8P9PGT
ZpicKbrnAgMBAAECggEAd3yTQEQHR91/ASVfKPHMQns77eCbPVtekFusbugsMHYY
EPdHbqVMpvFvOMRc+f5Tzd15ziq6qBdbCJm8lThLm4iU0z1QrpaiDZ8vgUvDYM5Y
CXoZDli+uZWUTp60/n94fmb0ipZIChScsI2PrzOJWTvobvD/uso8MJydWc8zafQm
uqYzygOfjFZvU4lSfgzpefhpquy0JUy5TiKRmGUnwLb3TtcsVavjsn4QmNwLYgOF
2OE+R12ex3pAKTiRE6FcnE1xFIo1GKhBa2Otgw3MDO6Gg+kn8Q4alKz6C6RRlgaH
R7sYzEfJhsk/GGFTYOzXKQz2lSaStKt9wKCor04RcQKBgQDzPOu5jCTfayUo7xY2
jHtiogHyKLLObt9l3qbwgXnaD6rnxYNvCrA0OMvT+iZXsFZKJkYzJr8ZOxOpPROk
10WdOaefiwUyL5dypueSwlIDwVm+hI4Bs82MajHtzOozh+73wA+aw5rPs84Uix9w
VbbwaVR6qP/BV09yJYS5kQ7fmwKBgQDe2xjywX2d2MC+qzRr+LfU+1+gq0jjhBCX
WHqRN6IECB0xTnXUf9WL/VCoI1/55BhdbbEja+4btYgcXSPmlXBIRKQ4VtFfVmYB
kPXeD8oZ7LyuNdCsbKNe+x1IHXDe6Wfs3L9ulCfXxeIE84wy3fd66mQahyXV9iD9
CkuifMqUpQKBgQCiydHlY1LGJ/o9tA2Ewm5Na6mrvOs2V2Ox1NqbObwoYbX62eiF
53xX5u8bVl5U75JAm+79it/4bd5RtKux9dUETbLOhwcaOFm+hM+VG/IxyzRZ2nMD
1qcpY2U5BpxzknUvYF3RMTop6edxPk7zKpp9ubCtSu+oINvtxAhY/SkcIwKBgGP1
upcImyO2GZ5shLL5eNubdSVILwV+M0LveOqyHYXZbd6z5r5OKKcGFKuWUnJwEU22
6gGNY9wh7M9sJ7JBzX9c6pwqtPcidda2AtJ8GpbOTUOG9/afNBhiYpv6OKqD3w2r
ZmJfKg/qvpqh83zNezgy8nvDqwDxyZI2j/5uIx/RAoGBAMWRmxtv6H2cKhibI/aI
MTJM4QRjyPNxQqvAQsv+oHUbid06VK3JE+9iQyithjcfNOwnCaoO7I7qAj9QEfJS
MZQc/W/4DHJebo2kd11yoXPVTXXOuEwLSKCejBXABBY0MPNuPUmiXeU0O3Tyi37J
TUKzrgcd7NvlA41Y4xKcOqEA
-----END PRIVATE KEY-----
RSA;

        return [
            [Key\InMemory::plainText($rsaKey)],
            [Key\InMemory::base64Encoded(base64_encode($rsaKey))],
            [Key\InMemory::file(__DIR__ . '/rsa/private.key')],
            [Key\LocalFileReference::file(__DIR__ . '/rsa/private.key')],
            [Key\LocalFileReference::file('file://' . __DIR__ . '/rsa/private.key')],
        ];
    }
}
