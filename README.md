# AWS Coludfront certificate rotation
#

##  What is it?
##  -----------
Script SSl certificate rotation for ASW Cloufront distribution


##  The Latest Version

	version 1.0 2019.03.03


##  Whats new

version 1.0 2019.03.03
  
  + Initial version


##  How to install
For Ubuntu ( or any Debian distributive):
```bash
sudo apt-get install aws-cli git php php-xml php-xmlsimple
git clone https://github.com/ikorolev72/rotate_certificate_cloudfront.git
cd rotate_certificate_cloudfront
wget http://docs.aws.amazon.com/aws-sdk-php/v3/download/aws.phar
cp -pr ~/.aws ./
```

## Configuration
Open script in any editor and change your distributionId and certificatePath 
```php
$distributionId = "E383S9KZNLXXXX"; // set there your cloudfront distribution id
$certificatePath = "/etc/letsencrypt/live/my.domain.com"; // set there local path to your certificates
```

## How to run
Run this script by root ( this require for access to your local certificates), or change access rights for local certificates.
Also, this script can be run in crontab, this will be usefull if you use certbot-auto for renew your certificates.
Sample for crontab:
```bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
30 2 1,15 * * /usr/local/sbin/certbot-auto renew >> /var/log/le-renew.log 2>&1
45 2 1,15 * * php /home/ubuntu/rotate_certificate_cloudfront/rotate_key_cloudfront.php >> /var/log/rotate_key_cloudfront.log 2>&1
``` 

##  Bugs
##  ------------


  Licensing
  ---------
	GNU

  Contacts
  --------

     o korolev-ia [at] yandex.ru
     o http://www.unixpin.com
