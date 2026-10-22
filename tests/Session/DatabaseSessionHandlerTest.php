<?php

namespace Illuminate\Tests\Session;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Session\DatabaseSessionHandler;
use PDOException;
use PHPUnit\Framework\TestCase;

class DatabaseSessionHandlerTest extends TestCase
{
    public function testUnrelatedInsertQueryErrorsAreNotSwallowed()
    {
        $exception = new QueryException('sqlite', 'insert into "sessions"', [], new PDOException('NOT NULL constraint failed'));

        $query = $this->createMock(Builder::class);
        $query->method('useWritePdo')->willReturnSelf();
        $query->expects($this->once())->method('insert')->willThrowException($exception);
        $query->expects($this->never())->method('update');

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('table')->with('sessions')->willReturn($query);

        $handler = new DatabaseSessionHandler($connection, 'sessions', 120);
        $handler->setExists(false);

        $this->expectExceptionObject($exception);

        $handler->write('session-id', 'payload');
    }

    public function testDuplicateSessionInsertFallsBackToUpdate()
    {
        $exception = new UniqueConstraintViolationException(
            'sqlite', 'insert into "sessions"', [], new PDOException('UNIQUE constraint failed')
        );

        $query = $this->createMock(Builder::class);
        $query->method('useWritePdo')->willReturnSelf();
        $query->expects($this->once())->method('insert')->willThrowException($exception);
        $query->expects($this->once())->method('where')->with('id', 'session-id')->willReturnSelf();
        $query->expects($this->once())->method('update')->willReturn(1);

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('table')->with('sessions')->willReturn($query);

        $handler = new DatabaseSessionHandler($connection, 'sessions', 120);
        $handler->setExists(false);

        $this->assertTrue($handler->write('session-id', 'payload'));
    }
}
