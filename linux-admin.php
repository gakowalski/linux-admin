<?php

require 'common/functions.php';

extract(prepare_options(getopt('', [
  'help',
  'report',
  'enable:',
  'disable:',
]), [

]));

if ($argc == 1 || isset($help)) {
  $me = basename(__FILE__);
  echo "\n\tphp $me [OPTIONS]

  Possible options:

    --help        This screen.
    --enable=
    --disable=
      sudo-without-password
    --report
  ";
  exit;
}

$username = trim(`whoami`);
info("Running as user $username");

if (posix_getuid() == 0){
  info("Running with root privileges");
} else {
  failure("Running without root privileges");
}

if ($report) {

}

if ($enable) {
  switch ($enable) {
    case 'sudo-without-password':
      $file = "/etc/sudoers.d/$username-no-password-rule";
      if (false === file_put_contents($file, "$username ALL=(ALL) NOPASSWD:ALL")) {
        failure("Can't write to $file");
      } else {
        info("Special rules written to $file - now sudo will work without password");
      }
      break;
    default:
      info("Unknown option $enable - can't be enabled");
  }
}

if ($disable) {
  switch ($disable) {
    case 'sudo-without-password':
      $file = "/etc/sudoers.d/$username-no-password-rule";
      `rm $file`;
      break;
    default:
      info("Unknown option $enable - can't be enabled");
  }
}
