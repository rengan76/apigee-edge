<?php

include './vendor/autoload.php';

// Your organization name
$org = 'rangaprasadramanujam-eval';
// API endpoint in Apigee’s cloud
$endpoint = 'https://apigee.com/';
// Authenticated user for this organization.
// This user should have the ‘devadmin’ (or ‘orgadmin’) role on Edge.
$user = 'rangaprasad.ramanujam@onesky.com';
// Password for the above user
$pass = 'Ranprarak76@12';
// An array of other connection options
$options = array(
  'http_options' => array(
    'connection_timeout' => 4,
    'timeout' => 4
  )
);

$org_config = new Apigee\Util\OrgConfig($org, $endpoint, $user, $pass, $options);

$developer = new Apigee\ManagementAPI\Developer($org_config);


try {
  $developer->load('rangaprasad.ramanujam@se.com');
  $developer->setFirstName('Ran');
  $developer->setLastName('Ram');
  $developer->save();
  print "Developer updated!\n";
}
catch (Apigee\Exceptions\ResponseException $e) {
  print "Error".$e->getMessage();

}
echo "<pre>";
print_r($developer);

$app = new Apigee\ManagementAPI\DeveloperApp($org_config, $developer->getEmail());
try {
  $app_list = $app->getListDetail();
  foreach ($app_list as $my_app) {
    print $my_app->getName() . "\n";
  }
}
catch (Apigee\Exceptions\ResponseException $e) {
  print $e->getMessage();
}