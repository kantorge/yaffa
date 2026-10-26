<?php

namespace App\Enums;

enum SameEventOutcome: string
{
    /** The very same content was already received. */
    case ExactRepeat = 'exact_repeat';

    /** A committed transaction records the same purchase. */
    case SameEventTransaction = 'same_event_transaction';

    /** Another open document describes the same purchase. */
    case SameEventDocument = 'same_event_document';

    /** The key matches, but nothing says whether it is the same purchase or a repeat. */
    case Candidate = 'candidate';

    /** Same payee and date window, but the amount is only close. */
    case NearMatch = 'near_match';

    case None = 'none';
}
