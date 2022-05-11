<?php 
    namespace Magistraal\Messages;

    function get($id) {
        return \Magistraal\Messages\format(\Magister\Session::messageGet($id));
    }

    function get_all($top = 1000, $skip = 0) {
        return \Magistraal\Messages\format_all(\Magister\Session::messageList($top, $skip), $top, $skip);
    }

    function delete($id) {
        return \Magister\Session::messageDelete($id);
    }

    function format($message) {
        $formatted = [
            'content'         => $message['inhoud'] ?? '',
            'folder_id'       => $message['mapId'],
            'forwarded_at'    => $message['doorgestuurdOp'],
            'has_attachments' => $message['heeftBijlagen'],
            'attachments'     => [],
            'id'              => $message['id'],
            'priority'        => $message['heeftPrioriteit'] ? 1 : 0,
            'read'            => $message['isGelezen'],
            'recipients'      => [
                'bcc'             => ['names' => [], 'list' => []],
                'cc'              => ['names' => [], 'list' => []],
                'to'              => ['names' => [], 'list' => []]
            ],
            'replied_at'      => $message['beantwoordOp'],
            'sender'          => [
                'id'              => $message['afzender']['id'],
                'name'            => $message['afzender']['naam']
            ],
            'sent_at'         => $message['verzondenOp'],
            'subject'         => $message['onderwerp']
        ];
        
        // Store recipient TO name and id
        foreach ($message['ontvangers'] ?? [] as $recipient) {
            $formatted['recipients']['to']['list'][] = [
                'id'   => $recipient['id'],
                'name' => $recipient['weergavenaam']
            ];

            $formatted['recipients']['to']['names'][] = $recipient['weergavenaam'];
        }

        // Store recipient CC name and id
        foreach ($message['kopieOntvangers'] ?? [] as $recipient) {
            $formatted['recipients']['cc']['list'][] = [
                'id'   => $recipient['id'],
                'name' => $recipient['weergavenaam']
            ];

            $formatted['recipients']['cc']['names'][] = $recipient['weergavenaam'];
        }

        // Store recipient BCC name and id
        foreach ($message['blindeKopieOntvangers ']?? [] as $recipient) {
            $formatted['recipients']['bcc']['list'][] = [
                'id'   => $recipient['id'],
                'name' => $recipient['weergavenaam']
            ];

            $formatted['recipients']['bcc']['names'][] = $recipient['weergavenaam'];
        }

        // Add attachments
        if(isset($message['bijlagen']) && is_array($message['bijlagen']) && count($message['bijlagen']) > 0) {
            foreach($message['bijlagen'] as $attachment) {
                $formatted['attachments'][] = [
                    'id'        => $attachment['id'],
                    'name'      => pathinfo($attachment['naam'], PATHINFO_FILENAME),
                    'mime_type' => $attachment['contentType'],
                    'type'      => pathinfo($attachment['naam'], PATHINFO_EXTENSION),
                    'modified'  => $attachment['gewijzigdOp'],
                    'location'  => $attachment['links']['download']['href'] ?? null
                ];
            }
        }

        return $formatted;
    }

    function format_all($messages, $top, $skip) {
        $formatted = [];
            
        foreach ($messages as $message) {
            $formatted[] = \Magistraal\Messages\format($message);
        }

        return $formatted;
    }

    function mark_read($id, $read) {
        return \Magister\Session::messageMarkRead($id, $read);
    }

    function send($to = [], $cc = [], $bcc = [], $subject = null, $content = null, $priority = null) {
        array_walk($to, function(&$v, $k) {
            $v = ['id' => $v, 'type' => 'persoon'];
        });

        array_walk($cc, function(&$v, $k) {
            $v = ['id' => $v, 'type' => 'persoon'];
        });

        array_walk($bcc, function(&$v, $k) {
            $v = ['id' => $v, 'type' => 'persoon'];
        });

        return \Magister\Session::messageSend($to, $cc, $bcc, $subject, $content, $priority);
    }
?>