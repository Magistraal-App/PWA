<?php 
    namespace Magistraal\Messages;

    function get($id, $filter = null) {
        return \Magistraal\Messages\format(\Magister\Session::messageGet($id) ?? [], $filter);
    }

    function get_all($top = 1000, $skip = 0, $folder, $filter = null) {
        return \Magistraal\Messages\format_all(\Magister\Session::messageList($top, $skip, $folder) ?? [], $top, $skip, $folder, $filter);
    }

    function delete($id) {
        return \Magister\Session::messageDelete($id);
    }

    function format($message, $filter = null) {
        $formatted = [
            'content'         => $message['inhoud'] ?? '',
            'has_attachments' => $message['heeftBijlagen'] ?? null,
            'attachments'     => [],
            'id'              => $message['id'] ?? null,
            'priority'        => $message['heeftPrioriteit'] ? 1 : 0,
            'read'            => $message['isGelezen'] ?? null,
            'recipients'      => [
                'bcc'             => ['names' => [], 'list' => []],
                'cc'              => ['names' => [], 'list' => []],
                'to'              => ['names' => [], 'list' => []]
            ],
            'sender'          => [
                'id'              => $message['afzender']['id'] ?? null,
                'name'            => $message['afzender']['naam'] ?? null
            ],
            'sent_at'         => $message['verzondenOp'] ?? null,
            'subject'         => $message['onderwerp'] ?? null
        ];
        
        // Store recipient TO name and id
        foreach ($message['ontvangers'] ?? [] as $recipient) {
            $formatted['recipients']['to']['list'][] = [
                'id'   => $recipient['id'] ?? null,
                'name' => $recipient['weergavenaam'] ?? null
            ];

            $formatted['recipients']['to']['names'][] = $recipient['weergavenaam'] ?? null;
        }

        // Store recipient CC name and id
        foreach ($message['kopieOntvangers'] ?? [] as $recipient) {
            $formatted['recipients']['cc']['list'][] = [
                'id'   => $recipient['id'] ?? null,
                'name' => $recipient['weergavenaam'] ?? null
            ];

            $formatted['recipients']['cc']['names'][] = $recipient['weergavenaam'] ?? null;
        }

        // Store recipient BCC name and id
        foreach ($message['blindeKopieOntvangers ']?? [] as $recipient) {
            $formatted['recipients']['bcc']['list'][] = [
                'id'   => $recipient['id'] ?? null,
                'name' => $recipient['weergavenaam'] ?? null
            ];

            $formatted['recipients']['bcc']['names'][] = $recipient['weergavenaam'] ?? null;
        }

        // Add attachments
        if(isset($message['bijlagen']) && is_array($message['bijlagen']) && count($message['bijlagen']) > 0) {
            foreach($message['bijlagen'] as $attachment) {
                $formatted['attachments'][] = [
                    'id'        => $attachment['id'] ?? null,
                    'name'      => pathinfo($attachment['naam'] ?? null, PATHINFO_FILENAME),
                    'mime_type' => $attachment['contentType'] ?? null,
                    'type'      => pathinfo($attachment['naam'] ?? null, PATHINFO_EXTENSION),
                    'modified'  => $attachment['gewijzigdOp'] ?? null,
                    'location'  => $attachment['links']['download']['href'] ?? null
                ];
            }
        }

        return filter_items($formatted, $filter);
    }

    function format_all($messages, $top, $skip, $folder = 'inbox', $filter = null) {
        $amount_unread = 0;
        $items = [];
            
        foreach ($messages as $message) {
            $formatted = \Magistraal\Messages\format($message, $filter);

            if($message['isGelezen'] !== true) {
                $amount_unread++;
            }

            $items[] = $formatted;
        }

        return ['amount_unread' => $amount_unread, 'folder' => $folder, 'items' => $items];
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