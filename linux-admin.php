<?php

require 'common/functions.php';

extract(prepare_options(getopt('', [
  'help',
  'report',
  'list:',
  'user:',
  'enable:',
  'disable:',
]), [

]));

if ($argc == 1 || isset($help)) {
  $me = basename(__FILE__);
  echo "\n\tphp $me [OPTIONS]

  Possible options:

    --help        This screen.
    --list=       Get list of something
      users
    --user=       Set target user (if applicable)
    --enable=
    --disable=
      sudo-without-password
    --report
  ";
  exit;
}

info("Running as user " . trim(`whoami`));

if (posix_getuid() == 0){
  info("Running with root privileges");
} else {
  failure("Running without root privileges");
}

if (isset($report)) {

}

if (isset($list)) {
  switch ($list) {
    case 'users':
      execute(["awk -F':' '{ print $1}' /etc/passwd | sort"]);
      break;
    default:
      info("Unknown category $list - objects can't be listed");
  }
}

if (isset($enable)) {
  switch ($enable) {
    case 'sudo-without-password':
      if (false === isset($user)) {
        failure("No target user set");
      }
      $user_md5 = md5($user); //< rule files can't contain special characters
      $file = "/etc/sudoers.d/$user_md5-no-password-rule";
      if (false === file_put_contents($file, "$user ALL=(ALL) NOPASSWD:ALL")) {
        failure("Can't write to $file");
      } else {
        info("Special rules written to $file - now sudo will work without password");
      }
      break;
    default:
      info("Unknown option $enable - can't be enabled");
  }
}

if (isset($disable)) {
  switch ($disable) {
    case 'sudo-without-password':
      if (false === isset($user)) {
        failure("No target user set");
      }
      $user_md5 = md5($user); //< rule files can't contain special characters
      $file = "/etc/sudoers.d/$user_md5-no-password-rule";
      if (false === unlink($file)) {
        failure("Can't remove $file");
      } else {
        info("Removed special rules file $file");
      }
      break;
    default:
      info("Unknown option $disable - can't be enabled");
  }
}
