<?php

namespace Core;

use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use League\OAuth2\Client\Provider\GenericProvider;

class GraphMailer
{
    private Graph $graph;

    public function __construct()
    {
        $provider = new GenericProvider([
            'clientId'                => $_ENV['GRAPH_CLIENT_ID'],
            'clientSecret'            => $_ENV['GRAPH_CLIENT_SECRET'],
            'urlAuthorize'            => 'https://login.microsoftonline.com/' . $_ENV['GRAPH_TENANT_ID'] . '/oauth2/v2.0/authorize',
            'urlAccessToken'          => 'https://login.microsoftonline.com/' . $_ENV['GRAPH_TENANT_ID'] . '/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => '',
        ]);

        try {
            $accessToken = $provider->getAccessToken('client_credentials', [
                'scope' => 'https://graph.microsoft.com/.default'
            ]);
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            echo "Erro OAuth: " . $e->getMessage() . PHP_EOL;
            echo "Resposta Azure: " . $e->getResponseBody() . PHP_EOL;
            exit;
        }

        $this->graph = new Graph();
        $this->graph->setAccessToken($accessToken->getToken());
    }


    public function send(string $to, ?string $name, string $subject, string $html): void
    {
        $sender = $_ENV['MAIL_FROM'];

        $body = [
            "message" => [
                "subject" => $subject,
                "body" => [
                    "contentType" => "HTML",
                    "content" => $html
                ],
                "toRecipients" => [
                    [
                        "emailAddress" => [
                            "address" => $to
                        ]
                    ]
                ]
            ]
        ];

        /*$this->graph
            ->createRequest("POST", "/users/{$sender}/sendMail")
            ->attachBody($body)
            ->execute();*/
        $this->graph
            ->createRequest("POST", "/users/anderson.cavalcante@bsicapital.com.br/sendMail")
            ->attachBody($body)
            ->execute();
    }
}
