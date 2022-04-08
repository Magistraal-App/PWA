<?php 
    namespace Magistraal\Messages;

    function get_all($top = 1000, $skip = 0) {
        return \Magistraal\Messages\format_all(\Magister\Session::messageList($top, $skip), $top, $skip);
    }

    function format_all($messages, $top, $skip) {
        $result = [];
            
        foreach ($messages as $message) {
            $result[] = [
                'folder_id'       => $message['mapId'],
                'forwarded_at'    => strtotime($message['doorgestuurdOp']),
                'has_attachments' => $message['heeftBijlagen'],
                'id'              => $message['id'],
                'priority'        => $message['heeftPrioriteit'] ? 1 : 0,
                'read'            => $message['isGelezen'],
                'replied_at'      => strtotime($message['beantwoordOp']),
                'sender'          => [
                    'id'              => $message['afzender']['id'],
                    'name'            => $message['afzender']['naam']
                ],
                'sent_at'         => strtotime($message['verzondenOp']),
                'subject'         => $message['onderwerp']
            ];
        }

        return $result;
    }

    function get($id) {
        $response = \Magistraal\Browser\Browser::request(\Magister\Session::$domain."/api/berichten/berichten/{$id}/", [
            'method' => 'POST'
        ]);

        return \Magistraal\Messages\format($response['body']);
    }

    function format($message) {
        $result = [
            'content'         => $message['inhoud'],
            'folder_id'       => $message['mapId'],
            'forwarded_at'    => strtotime($message['doorgestuurdOp']),
            'has_attachments' => $message['heeftBijlagen'],
            'id'              => $message['id'],
            'priority'        => $message['heeftPrioriteit'] ? 1 : 0,
            'read'            => $message['isGelezen'],
            'recipients'      => [
                'bcc'             => ['names' => [], 'list' => []],
                'cc'              => ['names' => [], 'list' => []],
                'to'              => ['names' => [], 'list' => []]
            ],
            'replied_at'      => strtotime($message['beantwoordOp']),
            'sender'          => [
                'id'              => $message['afzender']['id'],
                'name'            => $message['afzender']['naam']
            ],
            'sent_at'         => strtotime($message['verzondenOp']),
            'subject'         => $message['onderwerp']
        ];
        
        // Store recipient TO name and id
        foreach ($message['ontvangers'] as $recipient) {
            $result['recipients']['to']['list'][] = [
                'id'   => $recipient['id'],
                'name' => $recipient['weergavenaam']
            ];

            $result['recipients']['to']['names'][] = $recipient['weergavenaam'];
        }

        // Store recipient CC name and id
        foreach ($message['kopieOntvangers'] as $recipient) {
            $result['recipients']['cc']['list'][] = [
                'id'   => $recipient['id'],
                'name' => $recipient['weergavenaam']
            ];

            $result['recipients']['cc']['names'][] = $recipient['weergavenaam'];
        }

        // Store recipient BCC name and id
        foreach ($message['blindeKopieOntvangers'] as $recipient) {
            $result['recipients']['bcc']['list'][] = [
                'id'   => $recipient['id'],
                'name' => $recipient['weergavenaam']
            ];

            $result['recipients']['bcc']['names'][] = $recipient['weergavenaam'];
        }

        return $result;
    }

    function mark_read($id, $read = true) {
        \Magistraal\Browser\Browser::request(\Magister\Session::$domain."/api/berichten/berichten/", [
            'method' => 'patch',
            'payload' => [
                'berichten' => [
                    [
                        'berichtId' => $id,
                        'operations' => [
                            [
                                'op' => 'replace',
                                'path' => '/IsGelezen',
                                'value' => $read
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        return true;
    }
?>