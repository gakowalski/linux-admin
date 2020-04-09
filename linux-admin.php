<?php

require 'common/functions.php';

extract(prepare_options(getopt('', [
  'help',
]), [

]));

if ($argc == 1 || isset($help)) {
  $me = basename(__FILE__);
  echo "\n\tphp $me [OPTIONS]

  Possible options:

    --help        This screen.
    --
  ";
  exit;
}

$username = `whoami`;
info("Running as user $username");

if (posix_getuid() == 0){
  info("Running with root privileges");
} else {
  failure("Running without root privileges");
}
