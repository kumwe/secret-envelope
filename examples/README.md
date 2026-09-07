# Runnable examples

Install Composer dependencies, then run `php examples/round-trip.php` and `php examples/rotation.php`.
`php examples/container.php` also needs the optional host `laminas/laminas-servicemanager:^4.5`.
All examples generate ephemeral demonstration keys, verify results and print no key or plaintext.
They start no transaction, write no file and perform no network I/O.
