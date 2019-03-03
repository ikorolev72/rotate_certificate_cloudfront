<?php
$basedir = dirname(__FILE__);
require "$basedir/aws.phar";

// distribution for your domain
$distributionId = "E383S9KZNLXXXX"; // set there your cloudfront distribution id
$certificatePath = "/etc/letsencrypt/live/my.domain.com"; // set there local path to your certificates

use Aws\CloudFront\CloudFrontClient;
use Aws\Credentials\CredentialProvider;
use Aws\Exception\AwsException;
use Aws\Iam\IamClient;

$provider = CredentialProvider::ini();
$profile = 'default';
// copy your credentials ( usual ~/.aws/credentials )  to current dir
$path = "$basedir/.aws/credentials"; // set these path to your credentials
$provider = CredentialProvider::ini($profile, $path);
$provider = CredentialProvider::memoize($provider);
$sharedConfig = [
    'region' => 'us-east-1',
    'version' => 'latest',
    'credentials' => $provider,
];

// new class instances
$clientCloudFront = new Aws\CloudFront\CloudFrontClient($sharedConfig);
$clientIam = new IamClient($sharedConfig);

try {
    $distributionConfigObj = $clientCloudFront->GetDistributionConfig([
        'Id' => $distributionId, //REQUIRED
    ]);
} catch (AwsException $e) {
    // output error message if fails
    writeToLog("Cannot get distribution with id $distributionId");
    writeToLog($e->getMessage());
    exit(1);
}
$distributionConfig["DistributionConfig"] = $distributionConfigObj['DistributionConfig'];
$distributionConfig['Id'] = $distributionId;
$distributionConfig['IfMatch'] = $distributionConfigObj['ETag'];
unset($distributionConfig['DistributionConfig']['ETag']);
$oldCertificatId = $distributionConfig['DistributionConfig']['ViewerCertificate']['IAMCertificateId'];

// list all certificates
try {
    $result = $clientIam->listServerCertificates();
} catch (AwsException $e) {
    // output error message if fails
    writeToLog("Cannot get list of certificates");
    writeToLog($e->getMessage());
    exit(1);
}

// search certificate name
foreach ($result['ServerCertificateMetadataList'] as $certificate) {
    if ($oldCertificatId == $certificate['ServerCertificateId']) {
        $oldCertificateName = $certificate['ServerCertificateName'];
        writeToLog("Found certificate name $oldCertificateName");
        break;
    }
}
if (empty($oldCertificateName)) {
    writeToLog("Cannot found required certificat name in certificate list");
    exit(1);
}

// read new certificate files
try {
    $dt = date("YmdHi", time());
    $ServerCertificateName = "letsencrypt.$dt";
    $Path = "/cloudfront/letsencrypt.$dt/"; // path for certificate in aws iam
    $CertificateBody = file_contents("$certificatePath/cert.pem");
    $CertificateChain = file_contents("$certificatePath/chain.pem");
    $PrivateKey = file_contents("$certificatePath/privkey.pem");
} catch (Exception $e) {
    // output error message if fails
    writeToLog("Cannot read certificate files");
    writeToLog($e->getMessage());
    exit(1);
}

// upload new certificate
try {
    $newCertificate = $clientIam->uploadServerCertificate([
        'CertificateBody' => $CertificateBody, // REQUIRED
        'CertificateChain' => $CertificateChain,
        'Path' => $Path,
        'PrivateKey' => $PrivateKey, // REQUIRED
        'ServerCertificateName' => $ServerCertificateName, // REQUIRED
    ]);
} catch (AwsException $e) {
    // output error message if fails
    writeToLog("Cannot upload new certificat");
    writeToLog($e->getMessage());
    exit(1);
}

$newCertificateId = $newCertificate['ServerCertificateMetadata']['ServerCertificateId'];
$distributionConfig['DistributionConfig']['ViewerCertificate']['IAMCertificateId'] = $newCertificateId;
$distributionConfig['DistributionConfig']['ViewerCertificate']['Certificate'] = $newCertificateId;

try {
    $newDistributionConfig = $clientCloudFront->updateDistribution($distributionConfig);
} catch (AwsException $e) {
    // output error message if fails
    writeToLog("Cannot update distribution with id $distributionId");
    writeToLog($e->getMessage());
    exit(1);
}

for ($i = 0; $i < 12; $i++) {
    try {
        $distribution = $clientCloudFront->GetDistribution([
            'Id' => $distributionId, //REQUIRED
        ]);
    } catch (AwsException $e) {
        // output error message if fails
        writeToLog("Cannot get updated distribution with id $distributionId");
        writeToLog($e->getMessage());
        exit(1);
    }
    writeToLog("Cloudfront distribution with id $distributionId now in status: " . $distribution['Distribution']['Status']);
    if ($distribution['Distribution']['Status'] == "Deployed") {
        writeToLog("All ok. Certificate uploaded and applied to distribution with id $distributionId. Distribution have 'Deployed' staus now");
        // remove old certificate
        try {
            $result = $clientIam->deleteServerCertificate([
                'ServerCertificateName' => $oldCertificateName, // REQUIRED
            ]);
        } catch (AwsException $e) {
            // output error message if fails
            writeToLog("Cannot remove old certificate");
            writeToLog($e->getMessage());
            exit(1);
        }
        exit(0);
    }
    sleep(300); // sleep 5 min and check status
}

writeToLog("Cannot check the Deployed staus of distribution with id $distributionId in 60 minutes. Distribution must be in 'Deployed' staus");
exit(1);

/**
 * writeToLog
 * function print messages to console
 *
 * @param    string $message
 * @return    string
 */
function writeToLog($message)
{
    $dt = date("Y-m-d H:i", time());
    //echo "$dt $message\n";
    fwrite(STDERR, "$dt $message\n");
}

function file_contents($path)
{
    if (!file_exists($path)) {
        throw new Exception("File '$path' do not exists or haven't required permissions");
    }
    $str = @file_get_contents($path);
    if ($str === false) {
        throw new Exception("Cannot access '$path' to read contents.");
    } else {
        return $str;
    }
}
