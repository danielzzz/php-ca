<?
/*--------------------------------------------------
 | PHP-CA
 | By Dan Zelisko & Tom Ptaszynski
 | Copyright (c) 2010 Daniel Zelisko
 | Email: daniel@aydmultimedia.com
 +--------------------------------------------------
 | bugs/suggestions to http://github.com/danielzzz/php-ca
 +--------------------------------------------------
 | This script has been created and released under
 | the GNU GPL and is free to use and redistribute
 | only if this copyright statement is not removed
 +--------------------------------------------------*/


// --- tar.gz helper class ----
require('./lib/archive.php');

//this should be unique for each client
$commonName = 'ayd-test';

// your openvnp network name
$networkName = 'testvpn';

// open vpn server config
$openVPNServer = 'your.remote.openvpn.server.com';
$openVPNPort = 1194;

// server key and cert
$serverCertPath = "./server_keys/ca.crt";
$serverKeyPath = './server_keys/ca.key';

// output directory - it should be writtable - don't forget a trailing slash
$outputDir = "./";


//--------------------------------------------------------------------
// you should not need to modify anything below this line
//--------------------------------------------------------------------
$tmpDir = $outputDir.$networkName.'-'.$commonName;

$filesToCompress = array();

//prepare tar.gzip archive
$outputFile = './'.$networkName.'-'.$commonName.'.tar.gz';
$archive = new gzip_file($outputFile);
$archive->set_options(array('basedir' => $outputDir, 'storepaths'=>0, 'overwrite' => 1, 'level' => 2));




$dn = array(
    "countryName" => 'ES', 
    "stateOrProvinceName" => 'Baleares', 
    "localityName" => 'Palma de Mallorca', 
    "organizationName" => 'MyORG', 
    "organizationalUnitName" => 'AYD test', 
    "commonName" => $commonName, 
    "emailAddress" => 'test@test.com'
);

$privkeypass = null;
$numberofdays = 3650;

//load previously generated server private key
$fp=fopen($serverKeyPath,"r");
$caData = fread($fp,8192);
fclose($fp);
// $passphrase is required if your key is encoded (suggested)
$caKey = openssl_get_privatekey($caData);

//load previously generated server cartificate
$fp=fopen($serverCertPath,"r");
$caCrt = fread($fp,8192);
fclose($fp);

//--------------- generating a new user cert and key -------------

// create private key for the user
$privkey = openssl_pkey_new();
openssl_pkey_export($privkey, $privatekey, $privkeypass);


//make certificate request for the user
$csr = openssl_csr_new($dn, $privatekey);
openssl_csr_export($csr, $csrStr);

//sign certificate request with the CA key
$sscert = openssl_csr_sign($csrStr, $caCrt, $caKey, $numberofdays);
openssl_x509_export($sscert, $publickey);

//create a tmp dir
mkdir($tmpDir);

//write a private key
echo "writting private key...\n";
echo $privatekey; // Will hold the exported PriKey
$path = $tmpDir."/".$commonName.'.key';
file_put_contents($path, $privatekey);
$archive->add_files($path);

//write an user cert
echo "writting ceritifate...\n";
echo $publickey;     // Will hold the exported Certificate
$path = $tmpDir."/".$commonName.'.crt';
file_put_contents($path, $publickey);
$archive->add_files($path);

//copy server certificate (we need it for openvpn config)
$path = $tmpDir.'/'.$networkName.'.crt';
copy('./server_keys/ca.crt', $path);
echo "copying server certificate...\n";
$archive->add_files($path);

//generate and write openvpn config file
$config = "client
dev tun
tun-mtu 1200
proto udp
remote $openVPNServer $openVPNPort
resolv-retry infinite
nobind


persist-key
persist-tun
ca /etc/openvpn/$networkName.crt
cert /etc/openvpn/$commonName.crt
key /etc/openvpn/{$commonName}.key


comp-lzo
verb 5

";

$path = $tmpDir.'/'.$networkName.'.conf';
$filesToCompress[] = $path;
file_put_contents($path, $config);
$archive->add_files($path);

echo "generated files are in: ".$tmpDir." directory \n";


$archive->create_archive();

if(isset($archive->errors) && count($archive->errors)>0) {
    echo "ERROR while creating a tar.gz archive\n";
    exit(1);   
} 

echo "{$outputFile} file created\n";
exit(0);
//remove tmp dir
?>
