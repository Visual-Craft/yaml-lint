<?php

namespace Symfony\Component\Yaml;

/**
 * @return bool
 */
function defined(): bool
{
    return true;
}

/**
 * @param $const
 * @return string
 */
function constant($const): string
{
    return (string) $const;
}
