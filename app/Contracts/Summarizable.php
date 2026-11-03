<?php

namespace App\Contracts;

/**
 * A record that can be listed compactly on another record's page (e.g. a company's documents).
 */
interface Summarizable
{
    /**
     * Get the compact representation: id, type, number, title, date, status label/colour,
     * total, currency, customer or supplier name and the link to the record.
     *
     * @return array<string, mixed>
     */
    public function toSummary(): array;
}
