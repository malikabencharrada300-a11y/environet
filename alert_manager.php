<?php
// alert_manager.php - Gestion intelligente des alertes
require_once 'config.php';

class AlertManager {
    private $pdo;
    private $user_id;
    
    // Seuils configurables
    private $thresholds = [
        'temperature_critical' => ['min' => null, 'max' => 28, 'severity' => 'critical'],
        'temperature_warning' => ['min' => 24, 'max' => 28, 'severity' => 'warning'],
        'temperature_normal' => ['min' => 18, 'max' => 24, 'severity' => 'info'],
        'temperature_low' => ['min' => null, 'max' => 18, 'severity' => 'warning'],
        
        'humidity_critical' => ['min' => 80, 'max' => null, 'severity' => 'critical'],
        'humidity_warning' => ['min' => 70, 'max' => 80, 'severity' => 'warning'],
        'humidity_normal' => ['min' => 40, 'max' => 70, 'severity' => 'info'],
        'humidity_low' => ['min' => 30, 'max' => 40, 'severity' => 'warning'],
        'humidity_critical_low' => ['min' => null, 'max' => 30, 'severity' => 'critical'],
        
        'signal_critical' => ['min' => null, 'max' => 40, 'severity' => 'critical'],
        'signal_warning' => ['min' => 40, 'max' => 60, 'severity' => 'warning'],
        'signal_normal' => ['min' => 60, 'max' => null, 'severity' => 'info']
    ];
    
    // Hystérésis pour éviter les oscillations (ex: 28.1 -> 27.9 -> 28.1)
    private $hysteresis = [
        'temperature' => 0.5,  // 0.5°C de marge
        'humidity' => 2,        // 2% de marge
        'signal' => 3           // 3% de marge
    ];
    
    public function __construct($user_id) {
        $this->pdo = getDBConnection();
        $this->user_id = $user_id;
    }
    
    /**
     * Vérifie un capteur et génère une alerte uniquement si l'état a changé
     */
    public function checkSensor($type, $value) {
        if ($value === null) return;
        
        $currentCategory = $this->categorizeValue($type, $value);
        $previousCategory = $this->getPreviousState($type);
        
        // Si l'état n'a pas changé, on ignore
        if ($currentCategory === $previousCategory) {
            $this->updateLastChecked($type, $value);
            return null;
        }
        
        // Vérifier l'hystérésis pour éviter les oscillations
        if ($previousCategory && !$this->isGenuineChange($type, $value, $previousCategory, $currentCategory)) {
            return null;
        }
        
        // Générer l'alerte car l'état a vraiment changé
        $alertData = $this->createAlertFromCategory($type, $value, $currentCategory, $previousCategory);
        
        // Mettre à jour l'état
        $this->saveCurrentState($type, $currentCategory, $value);
        
        return $alertData;
    }
    
    /**
     * Catégorise une valeur selon les seuils
     */
    private function categorizeValue($type, $value) {
        foreach ($this->thresholds as $category => $threshold) {
            // Extraire le type de base (temperature, humidity, signal)
            $baseType = explode('_', $category)[0];
            
            if ($baseType !== $type) continue;
            
            $min = $threshold['min'];
            $max = $threshold['max'];
            
            // Vérifier si la valeur est dans la plage
            if ($min !== null && $max !== null) {
                if ($value > $min && $value <= $max) return $category;
            } elseif ($min !== null && $value > $min) {
                return $category;
            } elseif ($max !== null && $value <= $max) {
                return $category;
            }
        }
        
        return $type . '_normal';
    }
    
    /**
     * Récupère l'état précédent
     */
    private function getPreviousState($type) {
        $stmt = $this->pdo->prepare("
            SELECT current_state FROM alert_state 
            WHERE user_id = ? AND alert_type = ?
        ");
        $stmt->execute([$this->user_id, $type]);
        $result = $stmt->fetch();
        
        return $result ? $result['current_state'] : null;
    }
    
    /**
     * Vérifie si le changement est réel (avec hystérésis)
     */
    private function isGenuineChange($type, $value, $previousCategory, $currentCategory) {
        $prevValue = $this->getPreviousValue($type);
        
        if ($prevValue === null) return true;
        
        $hysteresisValue = $this->hysteresis[$type] ?? 1;
        $difference = abs($value - $prevValue);
        
        // Si la différence est inférieure à l'hystérésis, ignorer
        return $difference >= $hysteresisValue;
    }
    
    /**
     * Récupère la dernière valeur connue
     */
    private function getPreviousValue($type) {
        $stmt = $this->pdo->prepare("
            SELECT last_value FROM alert_state 
            WHERE user_id = ? AND alert_type = ?
        ");
        $stmt->execute([$this->user_id, $type]);
        $result = $stmt->fetch();
        
        return $result ? $result['last_value'] : null;
    }
    
    /**
     * Met à jour la date de dernière vérification
     */
    private function updateLastChecked($type, $value) {
        $stmt = $this->pdo->prepare("
            UPDATE alert_state 
            SET last_checked = NOW(), last_value = ? 
            WHERE user_id = ? AND alert_type = ?
        ");
        $stmt->execute([$value, $this->user_id, $type]);
    }
    
    /**
     * Sauvegarde l'état actuel
     */
    private function saveCurrentState($type, $state, $value) {
        $stmt = $this->pdo->prepare("
            INSERT INTO alert_state (user_id, alert_type, current_state, last_value, last_checked) 
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                current_state = VALUES(current_state),
                last_value = VALUES(last_value),
                last_checked = NOW()
        ");
        $stmt->execute([$this->user_id, $type, $state, $value]);
    }
    
    /**
     * Crée une alerte à partir de la catégorie
     */
    private function createAlertFromCategory($type, $value, $currentCategory, $previousCategory) {
        // Ne pas alerter si on passe à l'état normal
        if (strpos($currentCategory, 'normal') !== false && strpos($previousCategory, 'normal') !== false) {
            return null;
        }
        
        $threshold = $this->thresholds[$currentCategory] ?? null;
        if (!$threshold) return null;
        
        $severity = $threshold['severity'];
        $unit = $this->getUnit($type);
        
        // Message personnalisé selon le changement
        if ($previousCategory === null) {
            // Premier état détecté
            $message = ucfirst($type) . " {$this->getStateLabel($currentCategory)} : {$value}{$unit}";
            $title = "Initial {$type} state detected";
        } else {
            // Changement d'état
            $direction = $this->isWorsening($previousCategory, $currentCategory) ? 'increased' : 'decreased';
            $message = ucfirst($type) . " {$this->getStateLabel($currentCategory)} : {$value}{$unit} ({$direction} from previous state)";
            $title = ucfirst($type) . " state changed";
        }
        
        // Créer l'alerte dans la base
        $alertId = createAlert($this->user_id, $type, $message, $severity);
        
        return [
            'id' => $alertId,
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
            'title' => $title,
            'value' => $value,
            'previous_state' => $previousCategory,
            'current_state' => $currentCategory
        ];
    }
    
    /**
     * Vérifie si l'état s'aggrave
     */
    private function isWorsening($previousState, $currentState) {
        $severityOrder = ['info', 'warning', 'critical'];
        
        $prevSeverity = $this->getSeverityFromState($previousState);
        $currSeverity = $this->getSeverityFromState($currentState);
        
        return array_search($currSeverity, $severityOrder) > array_search($prevSeverity, $severityOrder);
    }
    
    /**
     * Récupère la sévérité d'un état
     */
    private function getSeverityFromState($state) {
        if (strpos($state, 'critical') !== false) return 'critical';
        if (strpos($state, 'warning') !== false) return 'warning';
        return 'info';
    }
    
    /**
     * Retourne un label lisible pour un état
     */
    private function getStateLabel($category) {
        $labels = [
            'temperature_critical' => 'critical (high)',
            'temperature_warning' => 'warning (elevated)',
            'temperature_normal' => 'normal',
            'temperature_low' => 'low',
            'humidity_critical' => 'critical (very high)',
            'humidity_warning' => 'warning (high)',
            'humidity_normal' => 'normal',
            'humidity_low' => 'low',
            'humidity_critical_low' => 'critical (very low)',
            'signal_critical' => 'critical (very weak)',
            'signal_warning' => 'warning (weak)',
            'signal_normal' => 'normal'
        ];
        
        return $labels[$category] ?? $category;
    }
    
    /**
     * Retourne l'unité selon le type
     */
    private function getUnit($type) {
        $units = [
            'temperature' => '°C',
            'humidity' => '%',
            'signal' => '%'
        ];
        
        return $units[$type] ?? '';
    }
}
?>