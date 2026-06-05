<?php
namespace Lukiman\Models;

use Lukiman\Cores\Model;

class Schema extends Model {
	protected string $table = 'schema_sync';
	protected string $prefix = 'schm';
	protected string $primaryKey = 'schmId';
}
