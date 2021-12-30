<?php

namespace SyntaxErrorInQueryFunctionRuleTest;

use Deployer\DbCredentials;

class Foo
{
    public function syntaxError(DbCredentials $dbCredentials)
    {
        \Deployer\runMysqlQuery('SELECT email adaid WHERE gesperrt freigabe1u1 FROM ada', $dbCredentials);
    }

    public function validQuery(DbCredentials $dbCredentials)
    {
        \Deployer\runMysqlQuery('SELECT email, adaid, gesperrt, freigabe1u1 FROM ada', $dbCredentials);
    }
}
