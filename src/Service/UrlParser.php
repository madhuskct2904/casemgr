<?php

namespace App\Service;

class UrlParser
{
    private string $frontendDomain;

    public function __construct(string $frontendDomain)
    {
        $this->frontendDomain = $frontendDomain;
    }


    /**
     * Remove the query string, protocol, and path from a URL, parse out the subdomain,
     * and append it to the FRONTEND_DOMAIN env var
     *
     * @param  mixed $url The URL to normalize
     * @return string
     */
    public function normalize(?string $url): ?string
    {
        if (is_null($url)) {
            return null;
        } else {
            $subdomain = $this->_getSubdomain($url);
            $convertedUrl = $this->_appendFrontendUrlToSubdomain($subdomain);
            return $convertedUrl;
        }
    }

    private function _getSubdomain(?string $url): ?string
    {
        if (is_null($url)) {
            return null;
        } else {
            if (preg_match('/^http/', $url)) {
                $parsedUrl = parse_url($url);

                if (isset($parsedUrl['host'])) {
                    $host = $parsedUrl['host'];
                } else {
                    return null;
                }
            } else {
                $host = $url;
            }

            $patternsToRemove = [
                '/' . $this->frontendDomain . '$/i',
                '/localhost$/i',
                '/^www\./i',
                '/\.$/i'
            ];

            return preg_replace($patternsToRemove, '', $host);
        }
    }

    private function _appendFrontendUrlToSubdomain(?string $subdomain): ?string
    {
        if (empty($subdomain)) {
            return null;
        } else {
            return rtrim(sprintf('%s.%s', $subdomain, $this->frontendDomain), '.');
        }
    }
}
