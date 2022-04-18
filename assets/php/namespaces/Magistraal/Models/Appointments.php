<?php 
    namespace Magistraal\Appointments;

    function get($id) {
        return \Magistraal\Appointments\format(\Magister\Session::appointmentGet($id));
    }

    function get_all($iso_from, $iso_to) {
        return \Magistraal\Appointments\format_all(\Magister\Session::appointmentList($iso_from, $iso_to), $iso_from, $iso_to);
    }

    function create($formatted) {
        return \Magister\Session::appointmentCreate(\Magistraal\Appointments\unformat($formatted));
    }

    function delete($id) {
        return \Magister\Session::appointmentDelete($id);
    }

    function finish($id, $finished = true) {       
        return \Magister\Session::appointmentFinish($id, $finished);
    }

    function format($appointment) {
        // Scheid de meeting link van de inhoud
        list($content, $meeting_link) = \Magistraal\Appointments\seperate_lesson_content($appointment['Inhoud']);

        // Bereken content length
        $content_length = strlen(trim(strip_tags($content)));

        // Maak lokatie leeg als het aanhalingstekens of streepjes zijn
        if(in_array($appointment['Lokatie'], ['""', '\'\'', '-', null, 'null'])) {
            $appointment['Lokatie'] = '';
        }
        
        $formatted = [
            'all_day'          => $appointment['DuurtHeleDag'] ?? false,
            'has_attachments'  => $appointment['HeeftBijlagen'] ?? false,
            'attachments'      => [],
            'content_length'   => $content_length ?? 0,
            'content_text'     => strip_tags(str_replace(['<br>', '</p>'], [' ', '</p> '], $content)) ?? '',
            'content'          => $content ?? '',
            'designation'      => $appointment['Omschrijving'] ?? '',
            'duration'         => [
                'seconds'           => (strtotime($appointment['Einde']) - strtotime($appointment['Start'])),
                'lessons'           => ($appointment['LesuurTotMet']+1 ?? 0) - ($appointment['LesuurVan'] ?? 0) 
            ],
            'end'              => [
                'time'              => $appointment['Einde'],
                'lesson'            => $appointment['LesuurTotMet'] ?? 0
            ],  
            'facility'         => $appointment['Lokatie'] ?? '',
            'finished'         => $appointment['Afgerond'] ?? false,
            'has_meeting_link' => $meeting_link == '' ? false : true,
            'id'               => $appointment['Id'],
            'info_type'        => \Magistraal\Appointments\remap_info_type($appointment['InfoType']) ?? 'unknown',
            'meeting_link'     => $meeting_link ?? '#',
            'start'            => [
                'time'              => $appointment['Start'],
                'lesson'            => $appointment['LesuurVan'] ?? 0
            ],  
            'status'           => \Magistraal\Appointments\remap_status($appointment['Status']) ?? 'unknown',
            'subjects'         => array_column($appointment['Vakken'] ?? [], 'Naam') ?? [],
            'teachers'         => array_column($appointment['Docenten'] ?? [], 'Naam') ?? [],
            'type'             => \Magistraal\Appointments\remap_type($appointment['Type']) ?? 'unknown'
        ];

        if(isset($appointment['Bijlagen']) && is_array($appointment['Bijlagen']) && count($appointment['Bijlagen']) > 0) {
            foreach($appointment['Bijlagen'] as $attachment) {
                $formatted['attachments'][] = [
                    'id'        => $attachment['Id'],
                    'name'      => pathinfo($attachment['Naam'], PATHINFO_FILENAME),
                    'mime_type' => $attachment['ContentType'],
                    'type'      => pathinfo($attachment['Naam'], PATHINFO_EXTENSION),
                    'modified'  => $attachment['Datum'],
                    'location'  => $attachment['Links'][0]['Href'] ?? null
                ];
            }
        }

        return $formatted;
    }

    function unformat($formatted) {
        return [
            'Id'           => $formatted['id'] ?? 0,
            'Lokatie'      => $formatted['facility'] ?? '',
            'Start'        => $formatted['start'] ?? null,
            'Einde'        => $formatted['end'] ?? null,
            'Omschrijving' => $formatted['designation'] ?? '',
            'Inhoud'       => $formatted['content'] ?? '',
            'Type'         => 1,
            'Status'       => 2,
            'InfoType'     => 6
        ];
    }

    function format_all($appointments, $iso_from, $iso_to) {
        $unix_from = strtotime($iso_from);
        $unix_to   = strtotime($iso_to);

        $formatted = [];

        // Maak een lijst met alle dagen tussen $iso_from en $iso_to als keys
        for ($unix=$unix_from; $unix <= $unix_to;) { 
            $formatted[date('Y-m-d', $unix)] = ['time' => date_iso($unix), 'unix' => $unix, 'appointments' => []];
            $unix = strtotime('+1 day', $unix);
        }

        foreach ($appointments as $appointment) {
            $start_date = date('Y-m-d', strtotime($appointment['Start']));

            // Sommige afspraken vallen buiten het gekozen tijdsbestek
            if(!isset($formatted[$start_date])) {
                continue;
            }

            $formatted[$start_date]['appointments'][] = \Magistraal\Appointments\format($appointment);
        }

        return $formatted;
    }
    
    function seperate_lesson_content($content) {
        // Haal meeting link uit inhoud
        preg_match('/https:\/\/teams.microsoft.com\/l\/meetup-join\/.*?(?=")/', $content, $meeting_link);
        $meeting_link = $meeting_link[0] ?? null;

        // Verwijder alle MS Teams links uit de inhoud
        $content = preg_replace('/<a(?:(?!<a|<\/a>)[\s\S])*?(href="https:\/\/teams.microsoft.com|href="https:\/\/support.office.com)[\s\S]*?<\/a>/', '', $content);

        // Verwijder resten
        $content = str_replace(['<hr>', '<span> | </span>', '<p></p>', '<p> </p>', '<p><br></p>'], '', $content);

        return [$content, $meeting_link];
    }

    function remap_info_type($int) {
        switch($int) {
            case 0: return 'none';         // Geen
            case 1: return 'homework';     // Huiswerk
            case 2: return 'test';         // Proefwerk
            case 3: return 'exam';         // Tentamen
            case 4: return 'test_written'; // Schriftelijke overhoring
            case 5: return 'test_oral';    // Mondelijke overhoring
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