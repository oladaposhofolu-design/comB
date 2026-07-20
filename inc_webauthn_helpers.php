<?php
// ============================================================
// webauthn_helpers.php
// Minimal, dependency-free WebAuthn (FIDO2) helper functions.
//
// Implements just enough of the WebAuthn spec to register and
// verify ES256 (P-256 ECDSA) fingerprint/Face ID credentials —
// no Composer/external library required, so this runs on
// InfinityFree's shared hosting.
//
// Covers: base64url encoding, a minimal CBOR decoder (enough
// for authenticatorData / COSE keys), converting a COSE EC2
// public key into a PEM the openssl_verify() function can use,
// and parsing the authenticatorData structure.
// ============================================================

// ---------- Base64URL ----------
function b64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function b64url_decode($data) {
    $data = strtr($data, '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad) { $data .= str_repeat('=', 4 - $pad); }
    return base64_decode($data);
}

// ---------- Minimal CBOR Decoder ----------
// Supports the subset of CBOR used inside WebAuthn structures:
// unsigned/negative ints, byte strings, text strings, arrays,
// maps, booleans, null, and simple floats (rarely used here).
class CborReader {
    private $data;
    private $offset = 0;

    public function __construct($data) {
        $this->data = $data;
    }

    public function remaining() {
        return strlen($this->data) - $this->offset;
    }

    private function readByte() {
        $b = ord($this->data[$this->offset]);
        $this->offset += 1;
        return $b;
    }

    private function readBytes($n) {
        $b = substr($this->data, $this->offset, $n);
        $this->offset += $n;
        return $b;
    }

    private function readUint($n) {
        $bytes = $this->readBytes($n);
        $val = 0;
        for ($i = 0; $i < $n; $i++) {
            $val = ($val << 8) | ord($bytes[$i]);
        }
        return $val;
    }

    private function readLength($additionalInfo) {
        if ($additionalInfo < 24) return $additionalInfo;
        if ($additionalInfo == 24) return $this->readUint(1);
        if ($additionalInfo == 25) return $this->readUint(2);
        if ($additionalInfo == 26) return $this->readUint(4);
        if ($additionalInfo == 27) return $this->readUint(8);
        throw new Exception("Unsupported CBOR length encoding");
    }

    public function decode() {
        $initial = $this->readByte();
        $majorType = $initial >> 5;
        $additionalInfo = $initial & 0x1F;

        switch ($majorType) {
            case 0: // unsigned int
                return $this->readLength($additionalInfo);
            case 1: // negative int
                return -1 - $this->readLength($additionalInfo);
            case 2: // byte string
                $len = $this->readLength($additionalInfo);
                return $this->readBytes($len);
            case 3: // text string
                $len = $this->readLength($additionalInfo);
                return $this->readBytes($len);
            case 4: // array
                $len = $this->readLength($additionalInfo);
                $arr = [];
                for ($i = 0; $i < $len; $i++) { $arr[] = $this->decode(); }
                return $arr;
            case 5: // map
                $len = $this->readLength($additionalInfo);
                $map = [];
                for ($i = 0; $i < $len; $i++) {
                    $key = $this->decode();
                    $val = $this->decode();
                    $map[$key] = $val;
                }
                return $map;
            case 7: // simple/float
                if ($additionalInfo == 20) return false;
                if ($additionalInfo == 21) return true;
                if ($additionalInfo == 22) return null;
                // floats not needed for our use case
                if ($additionalInfo == 27) { $this->readBytes(8); return null; }
                if ($additionalInfo == 26) { $this->readBytes(4); return null; }
                return null;
            default:
                throw new Exception("Unsupported CBOR major type: $majorType");
        }
    }
}

function cbor_decode($data) {
    $reader = new CborReader($data);
    return $reader->decode();
}

// ---------- Parse authenticatorData ----------
// Layout: rpIdHash(32) | flags(1) | signCount(4) | [attestedCredentialData] | [extensions]
function parse_authenticator_data($raw) {
    $rpIdHash = substr($raw, 0, 32);
    $flags = ord($raw[32]);
    $signCount = unpack('N', substr($raw, 33, 4))[1];

    $result = [
        'rpIdHash' => $rpIdHash,
        'flags' => $flags,
        'signCount' => $signCount,
        'userPresent' => (bool)($flags & 0x01),
        'userVerified' => (bool)($flags & 0x04),
        'attestedCredentialDataIncluded' => (bool)($flags & 0x40),
        'credentialId' => null,
        'credentialPublicKey' => null,
    ];

    $offset = 37;
    if ($result['attestedCredentialDataIncluded']) {
        $aaguid = substr($raw, $offset, 16); $offset += 16;
        $credIdLen = unpack('n', substr($raw, $offset, 2))[1]; $offset += 2;
        $credId = substr($raw, $offset, $credIdLen); $offset += $credIdLen;

        // Remaining bytes contain the CBOR-encoded COSE public key
        // (and possibly extensions after it). We only need the key,
        // so decode one CBOR item starting at $offset.
        $remaining = substr($raw, $offset);
        $reader = new CborReader($remaining);
        $coseKey = $reader->decode();

        $result['aaguid'] = $aaguid;
        $result['credentialId'] = $credId;
        $result['credentialPublicKey'] = $coseKey;
    }

    return $result;
}

// ---------- COSE EC2 key -> PEM ----------
// COSE key map keys (integers): 1=kty, 3=alg, -1=crv, -2=x, -3=y
// kty=2 (EC2), crv=1 (P-256), alg=-7 (ES256)
function cose_key_to_pem($coseKey) {
    if (!isset($coseKey[1]) || $coseKey[1] != 2) {
        throw new Exception("Only EC2 (P-256) COSE keys are supported");
    }
    $x = $coseKey[-2];
    $y = $coseKey[-3];

    // Uncompressed EC point: 0x04 || X || Y (32 bytes each for P-256)
    $point = "\x04" . $x . $y;

    // Build a DER SubjectPublicKeyInfo for a P-256 EC public key.
    // SEQUENCE {
    //   SEQUENCE { OID ecPublicKey, OID prime256v1 }
    //   BIT STRING { point }
    // }
    $oidEcPublicKey = hex2bin('06072a8648ce3d0201');       // 1.2.840.10045.2.1
    $oidPrime256v1  = hex2bin('06082a8648ce3d030107');     // 1.2.840.10045.3.1.7

    $algSeq = der_sequence($oidEcPublicKey . $oidPrime256v1);
    $bitString = "\x03" . der_length(strlen($point) + 1) . "\x00" . $point;
    $spki = der_sequence($algSeq . $bitString);

    $pem = "-----BEGIN PUBLIC KEY-----\n";
    $pem .= chunk_split(base64_encode($spki), 64, "\n");
    $pem .= "-----END PUBLIC KEY-----\n";
    return $pem;
}

function der_length($len) {
    if ($len < 128) return chr($len);
    $bytes = '';
    while ($len > 0) { $bytes = chr($len & 0xFF) . $bytes; $len >>= 8; }
    return chr(0x80 | strlen($bytes)) . $bytes;
}
function der_sequence($contents) {
    return "\x30" . der_length(strlen($contents)) . $contents;
}

// ---------- Verify an ES256 signature ----------
// $signedData = authData . SHA256(clientDataJSON)
// $signature is the DER-encoded ECDSA signature from the authenticator
function verify_es256($pem, $signedData, $signature) {
    $pubKey = openssl_pkey_get_public($pem);
    if (!$pubKey) { return false; }
    $result = openssl_verify($signedData, $signature, $pubKey, OPENSSL_ALGO_SHA256);
    return $result === 1;
}
?>
