<?php

enum DolibarrEventType: string
{
    case MEETING = 'AC_RDV';
    case CALL = 'AC_TEL';
    case EMAIL = 'AC_EMAIL';
    case NOTE = 'AC_NOTE';
    case TASK = 'AC_TODO';
    case OTHER = 'AC_OTH';
    case ACTION = 'AC_ACT';
}

/**
 * Class DolibarrEvent
 *
 * @property int $id
 * @property string $label
 * @property string $type_code
 * @property DateTime $datep
 * @property DateTime|null $datef
 * @property string $elementtype
 * @property int $elementid
 * @property int $userownerid
 * @property int|null $socid
 */
class DolibarrEvent extends DolibarrObject
{
    public static function createEventInProject(
        string $title,
        DateTime $start,
        ?DateTime $end,
        DolibarrEventType $type,
        int $projectId,
        int $userId,
        ?int $thirdpartyId = null
    ): ?self {
        return self::createEvent($title, $start, $end, $type, 'project', $projectId, $userId, $thirdpartyId);
    }

    public static function createEventInProposal(
        string $title,
        DateTime $start,
        ?DateTime $end,
        DolibarrEventType $type,
        int $proposalId,
        int $userId,
        ?int $thirdpartyId = null
    ): ?self {
        return self::createEvent($title, $start, $end, $type, 'propal', $proposalId, $userId, $thirdpartyId);
    }

    private static function createEvent(
        string $title,
        DateTime $start,
        ?DateTime $end,
        DolibarrEventType $type,
        string $elementType, // 'project' ou 'propal' (etc.)
        int $elementId,
        int $userId,
        ?int $thirdpartyId = null
    ): ?self {
        $apiKey = DOLIBARR_API_KEY;
        $url = DOLIBARR_REST_URL . "/agendaevents";
    
        $data = [
            'label' => $title,
            'type_code' => $type->value,
            'datep' => $start->getTimestamp(),
            'datef' => $end ? $end->getTimestamp() : '',
            'userownerid' => $userId,
            'fulldayevent' => 0,
            'transparency' => '1',
        ];
    
        // 🎯 Affectation selon le type de lien
        if ($elementType === 'project') {
            $data['fk_project'] = $elementId;
        } else {
            $data['elementtype'] = $elementType;
            $data['elementid'] = $elementId;
        }
    
        if ($thirdpartyId !== null) {
            $data['socid'] = $thirdpartyId;
        }
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "DOLAPIKEY: $apiKey",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
    
        $response = curl_exec($ch);
    
        if (curl_errno($ch)) {
            error_log('Erreur cURL : ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
    
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        if ($httpCode !== 200 && $httpCode !== 201) {
            error_log("Erreur HTTP $httpCode lors de la création de l'événement : $response");
            return null;
        }
    
        $eventData = json_decode($response);
    
        return $eventData ? new self($eventData) : null;
    }
    // Ajoute ici des getters si besoin, comme getId(), getTitle() etc.



    public static function initTestAction(): void
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'createEvent') {
            return;
        }

        if (!self::isCurrentUserAdminWP()) {
            echo "⛔ Accès interdit : vous devez être connecté en tant qu’administrateur WordPress.";
            return;
        }

        $start = new DateTime('now');
        $end = (clone $start)->modify('+1 hour');

        $event = self::createEventInProject(
            "Test automatique depuis WordPress vers Project",
            $start,
            $end,
            DolibarrEventType::ACTION,
            50, // ID de project fictive
            1,   // ID Dolibarr de l'utilisateur à utiliser (à adapter)
            195  // Tiers (facultatif)
        );

        if ($event) {
            echo "✅ Événement de test créé avec succès !<br/>";
            $event->printData();
        } else {
            echo "❌ Erreur lors de la création de l’événement de test.";
        }
    }

    private static function isCurrentUserAdminWP(): bool
    {
        if (!function_exists('is_user_logged_in')) {
            require_once ABSPATH . 'wp-includes/pluggable.php';
        }

        return is_user_logged_in() && current_user_can('administrator');
    }
}

add_action('init', function () {
    DolibarrEvent::initTestAction();
});
