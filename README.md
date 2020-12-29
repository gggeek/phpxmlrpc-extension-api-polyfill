A pure-php reimplementation of the API exposed by the native XML-RPC extension.

Originally bundled as part of the [phpxmlrpc/extras](https://github.com/gggeek/phpxmlrpc-extras) package.

*Work In Progress!*

Known differences from the original extension
---------------------------------------------

We strive to reproduce the same behaviour as the XML-RPC extension to the best "reasonable" extent.

This means that the following are _not_ goals of this package:

- being able to produce the same xml streams as the native extension, where "same" means byte-by-byte identical.
  Eg. whitespace and element indentation in the produced xml strings do differ

- reproducing behaviour of the native extension which is clearly buggy
  Eg. the native extension will produce invalid xmlrpc requests when specific values are passed to an `xmlrpc_encode_request` call

For a detailed list of known differences, see comments at the top of file [XmlRpc.php](src/XmlRpc.php).

Installation and usage
----------------------

The recommended way to install this library is via usage of Composer.

Running tests
-------------

The recommended way to run the library test suite is via the provided Docker containers.
A handy shell script is available that simplifies usage of Docker.

The full sequence of operations is:

    ./tests/ci/vm.sh build
    ./tests/ci/vm.sh start
    ./tests/ci/vm.sh runtests
    ./tests/ci/vm.sh stop

    # and, once you have finished all testing related work:
    ./tests/ci/vm.sh cleanup

By default tests are run using php 7.0 in a Container based on Ubuntu 16 Xenial.
You can change the version of PHP and Ubuntu in use by setting the environment variables PHP_VERSION and UBUNTU_VERSION
before building the Container.

To generate the code-coverage report, run `./tests/ci/vm.sh runcoverage`

License
-------
Use of this software is subject to the terms in the [license.txt](license.txt) file

[![License](https://poser.pugx.org/phpxmlrpc/polyfill-xmlrpc/license)](https://packagist.org/packages/phpxmlrpc/polyfill-xmlrpc)
[![Latest Stable Version](https://poser.pugx.org/phpxmlrpc/polyfill-xmlrpc/v/stable)](https://packagist.org/packages/phpxmlrpc/polyfill-xmlrpc)
[![Total Downloads](https://poser.pugx.org/phpxmlrpc/polyfill-xmlrpc/downloads)](https://packagist.org/packages/phpxmlrpc/polyfill-xmlrpc)

[![Build Status](https://travis-ci.com/gggeek/polyfill-xmlrpc.svg)](https://travis-ci.com/gggeek/polyfill-xmlrpc)
[![Code Coverage](https://scrutinizer-ci.com/g/gggeek/polyfill-xmlrpc/badges/coverage.png)](https://scrutinizer-ci.com/g/gggeek/polyfill-xmlrpc)
