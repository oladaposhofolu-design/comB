<?php
// ============================================================
// webauthn_register_verify.php
// Step 2 of fingerprint enrollment: receives the browser's
// attestation response from navigator.credentials.create(),
// verifies the challenge/origin, extracts the public key from
// the CBOR-encoded authenticatorData, and stores it.
// ============================================================
header('Content-Type: application/json');
session_start();
include 'db.php';
include 'webauthn_config.php';
include 'inc_webauthn_helpers.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($_SESSION['webauthn_challenge']) || !isset($_SESSION['webauthn_student_code'])) {
    echo json_encode(["success" => false, "message" => "Session expired. Please try again."]);
    exit;
}
$student_code = $_SESSION['webauthn_student_code'];
$expectedChallenge = $_SESSION['webauthn_challenge'];

try {
    $clientDataJSON = b64url_decode($data['clientDataJSON']);
    $attestationObject = b64url_decode($data['attestationObject']);
    $credentialId = $data['credentialId']; // already base64url as returned by the browser

    // ---- Verify clientDataJSON ----
    $clientData = json_decode($clientDataJSON, true);
    if ($clientData['type'] !== 'webauthn.create') {
        throw new Exception("Unexpected ceremony type.");
    }
    if (base64_encode(b64url_decode($clientData['challenge'])) !== $expectedChallenge) {
        throw new Exception("Challenge mismatch — possible replay attempt.");
    }
    if ($clientData['origin'] !== WEBAUTHN_ORIGIN) {
        throw new Exception("Origin mismatch.");
    }

    // ---- Parse attestationObject (CBOR map: fmt, attStmt, authData) ----
    $attestation = cbor_decode($attestationObject);
    $authData = parse_authenticator_data($attestation['authData']);

    if (!$authData['attestedCredentialDataIncluded']) {
        throw new Exception("No credential data in authenticator response.");
    }

    // Confirm this registration was for our RP ID
    if ($authData['rpIdHash'] !== hash('sha256', WEBAUTHN_RP_ID, true)) {
        throw new Exception("RP ID hash mismatch.");
    }
    if (!$authData['userVerified']) {
        throw new Exception("Biometric verification was not performed on this device.");
    }

    $publicKeyPem = cose_key_to_pem($authData['credentialPublicKey']);
    $credIdFromAuthData = b64url_encode($authData['credentialId']);

    // Store the credential
    $student_code_esc = mysqli_real_escape_string($conn, $student_code);
    $credId_esc = mysqli_real_escape_string($conn, $credIdFromAuthData);
    $pem_esc = mysqli_real_escape_string($conn, $publicKeyPem);
    $signCount = intval($authData['signCount']);

    $sql = "INSERT INTO webauthn_credentials (student_code, credential_id, public_key_pem, sign_count)
            VALUES ('$student_code_esc', '$credId_esc', '$pem_esc', $signCount)";

    if (mysqli_query($conn, $sql)) {
        unset($_SESSION['webauthn_challenge']);
        unset($_SESSION['webauthn_student_code']);
        unset($_SESSION['pin_verified_for']);
        echo json_encode(["success" => true, "message" => "Fingerprint enrolled successfully."]);
    } else {
        throw new Exception("Database error: " . mysqli_error($conn));
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
