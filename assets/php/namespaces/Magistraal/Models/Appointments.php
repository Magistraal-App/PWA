<?php 
    namespace Magistraal\Appointments;

    function get_all($from, $to) {
        return \Magistraal\Appointments\format_all(\Magister\Session::appointmentList($from, $to), $from, $to);
    }

    function format_all($appointments, $from, $to) {
        $result = [];

        if(isset($from) && isset($to)) {
            /* Generate an array with keys of dates between from and to */
            $appointments_unixes = range($from, $to, 86400);
            foreach ($appointments_unixes as $unix) {
                $result[date('Y-m-d', $unix)] = [
                    'unix' => $unix,
                    'appointments' => []
                ];
            }
        }

        foreach ($appointments as $appointment) {
            $start      = strtotime($appointment['Start']);
            $end        = strtotime($appointment['Einde']);
            $start_date = date('Y-m-d', $start);

            // Some appointments passed don't fit in the desired timespan, skip those
            if(!isset($result[$start_date])) {
                continue;
            }

            // Seperate Microsoft Teams meeting link from content
            list($content, $ms_teams_link) = \Magistraal\Appointments\seperate_lesson_content($appointment['Inhoud']);

            // Obtain solely link
            if(strpos($appointment['Inhoud'], '://teams.microsoft.com/l/meetup-join/') !== false) {
                $ms_teams_link = 'https://teams.microsoft.com/l/meetup-join/'.str_between('://teams.microsoft.com/l/meetup-join/', '"', $appointment['Inhoud']);
            }

            // Calculate content length
            $content_length = strlen(trim(strip_tags($content)));

            // Set type to none if there's no content
            if($appointment['InfoType'] && $content_length == 0) {
                $type = 'none';
            }

            // Clear facility when it's just a dash or quotes
            if(in_array($appointment['Lokatie'], ['""', '\'\'', '-', null, 'null'])) {
                $appointment['Lokatie'] = '';
            }

            $result[$start_date]['appointments'][] = [
                'all_day'          => $appointment['DuurtHeleDag'],
                'content_length'   => $content_length,
                'content_text'     => strip_tags(str_replace('</p>', '</p> ', $content)),
                'content'          => $content,
                'designation'      => $appointment['Omschrijving'],
                'duration'         => [
                    'seconds'           => ($end - $start),
                    'lessons'           => ($appointment['LesuurTotMet']+1 ?? 0) - ($appointment['LesuurVan'] ?? 0) 
                ],
                'end'              => [
                    'unix'              => $end,
                    'lesson'            => $appointment['LesuurTotMet'] ?? 0
                ],  
                'facility'         => $appointment['Lokatie'],
                'finished'         => $appointment['Afgerond'],
                'has_meeting_link' => $ms_teams_link == '' ? false : true,
                'id'               => $appointment['Id'],
                'info_type'        => \Magistraal\Appointments\remap_info_type($appointment['InfoType']),
                'meeting_link'     => $ms_teams_link,
                'start'            => [
                    'unix'              => $start,
                    'lesson'            => $appointment['LesuurVan'] ?? 0
                ],  
                'status'           => \Magistraal\Appointments\remap_status($appointment['Status']),
                'subjects'         => array_column($appointment['Vakken'], 'Naam'),
                'teachers'         => array_column($appointment['Docenten'], 'Naam'),
                'type'             => \Magistraal\Appointments\remap_type($appointment['Type'])
            ];
        }

        return $result;
    }

    function finish($id, $finished = true) {       
        return \Magister\Session::appointmentFinish($id, $finished);
    }

    function seperate_lesson_content($content) {
        $ms_teams_link = '';

        if(empty($content)) {
            return ['', ''];
        }

        $content_dom = new \DOMDocument();
        $content_dom->encoding = 'utf-8';
        $content_dom->loadHTML(utf8_decode($content));

        $content_nodes         = [];
        $remove_nodes          = [];
        $content_nodes['a']    = $content_dom->getElementsByTagName('a');
        $content_nodes['span'] = $content_dom->getElementsByTagName('span');
        $content_nodes['hr' ]  = $content_dom->getElementsByTagName('hr');
        $content_nodes['p' ]   = $content_dom->getElementsByTagName('p');

        foreach ($content_nodes as $nodes) {
            for ($i=0; $i < count($nodes); $i++) { 
                $node = $nodes->item($i);

                switch ($node->tagName) {
                    case 'a':
                        if(!$node->hasAttribute('href')) {
                            continue 2;
                        }

                        $href = $node->getAttribute('href');
                        if(stripos($href, '//teams.microsoft.com/l/meetup-join/') !== false) {
                            $ms_teams_link = $href;
                            $remove_nodes[] = $node;
                        } else if(
                            stripos($href, '//teams.microsoft.com/meetingOptions/') !== false ||
                            stripos($href, '//support.office.com/') !== false
                        ) {
                            $remove_nodes[] = $node;
                        }
                        break;
                    
                    case 'span':
                        if(trim($node->textContent) == '|') {
                            $remove_nodes[] = $node;
                        }
                        break;

                    case 'hr':
                        $remove_nodes[] = $node;
                        break;

                    case 'p':
                        if(trim($node->textContent) == '|') {
                            $remove_nodes[] = $node;
                        }
                        break;

                    default:
                        continue 2;
                }
            }
        }

        foreach ($remove_nodes as $node) {
            $node->parentNode->removeChild($node);
        }

        $content_html = $content_dom->saveHTML($content_dom->documentElement);

        $content_html = preg_replace('!\s+!', ' ', $content_html);

        // Remove semi-empty tags and everything outside body
        $content_html = str_between(
            '<body>', '</body>', str_replace([
                '<p><br></p>',
                '<p> </p>',
                '<p> | </p>',
                '<p>|</p>',
                '<p></p>'
            ], '', $content_html)
        );

        return [$content_html, $ms_teams_link];
    }

    function remap_info_type($int) {
        switch($int) {
            case 0: return 'none';         // Geen
            case 1: return 'homework';     // Huiswerk
            case 2: return 'test';         // Proefwerk
            case 3: return 'exam';         // Tentamen
            case 4: return 'exam_written'; // Schriftelijke overhoring
            case 5: return 'exam_oral';    // Mondelijke overhoring
            case 6: return 'info';         // Informatie
            case 7: return 'note';         // Aantekening
        }

        return 'unknown';
    }

    function remap_type($int) {
        switch($int) {
            case 0: return 'none';                 // Geen
            case 1: return 'personal';             // Persoonlijk
            case 2: return 'general';              // Algemeen
            case 3: return 'school_wide';          // Schoolbreed
            case 4: return 'internship';           // Stage
            case 5: return 'intake';               // Intake
            case 6: return 'free';                 // Roostervrij
            case 7: return 'kwt';                  // Keuzewerktijd
            case 8: return 'standby';              // Standby
            case 9: return 'blocked';              // Geblokkeerd
            case 10: return 'other';               // Overig
            case 11: return 'blocked_location';    // Geblokkeerde locatie
            case 12: return 'blocked_appointment'; // Geblokkeerde afspraak
            case 13: return 'appointment';         // Les
            case 14: return 'studyhouse';          // Studiehuis
            case 15: return 'free_study';          // Rosotervrije studie
            case 16: return 'schedule';            // Planning
            case 101: return 'measures';           // Maatregelen
            case 102: return 'presentations';      // Presentaties  
            case 103: return 'exam_schedule';      // Examenrooster
        }
        
        return 'unknown';
    }

    function remap_status($int) {
        switch($int) {
            case 1: return 'schedule';       // Automatisch geroosterd
            case 2: return 'schedule';       // Handmatig geroosterd
            case 3: return 'changed';        // Gewijzigd
            case 4: return 'canceled';       // Handmatig vervallen
            case 5: return 'canceled';       // Automatisch vervallen
            case 6: return 'in_use';         // In gebruik
            case 7: return 'finished';       // Afgesloten
            case 8: return 'used';           // Ingezet
            case 9: return 'moved';          // Verplaatst
            case 10: return 'changed_moved'; // Gewijzigd en verplaatst
        }

        return 'unknown';
    }
?>