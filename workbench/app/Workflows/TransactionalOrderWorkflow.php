<?php

namespace Workbench\App\Workflows;

use Pijler\Actions\Attributes\Transactional;

#[Transactional]
class TransactionalOrderWorkflow extends OrderWorkflow {}
