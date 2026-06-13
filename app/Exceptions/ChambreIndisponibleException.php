<?php

namespace App\Exceptions;

use Exception;

/**
 * ============================================================
 * EXCEPTION : ChambreIndisponibleException
 * ============================================================
 * Exception métier personnalisée levée quand une chambre
 * est déjà réservée pour les dates demandées.
 *
 * Pourquoi une exception personnalisée ?
 * → On peut la capturer spécifiquement dans le Controller
 *   et retourner exactement le bon code HTTP (409 Conflict)
 *   avec un message clair pour le frontend.
 *
 * Chemin : app/Exceptions/ChambreIndisponibleException.php
 * ============================================================
 */
class ChambreIndisponibleException extends Exception
{
    /**
     * Code HTTP par défaut : 409 Conflict
     * (conflict = conflit avec l'état actuel de la ressource)
     */
    protected $code = 409;

    public function __construct(string $message = "La chambre n'est pas disponible pour ces dates.")
    {
        parent::__construct($message, $this->code);
    }
}