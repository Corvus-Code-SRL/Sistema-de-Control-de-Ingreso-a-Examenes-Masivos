<?php

namespace Tests\Support;

use App\Support\CurrentUser;

/**
 * Resolver de usuario actual con ids fijados por la prueba, sin tocar config ni sesión.
 *
 * Solo sustituye lo que la prueba fijó; lo demás lo resuelve el resolver real, así un
 * actingAs() posterior sigue funcionando. Se instala con TestCase::actAsTeacher() y
 * TestCase::actAsUserId().
 */
class FakeCurrentUser extends CurrentUser
{
    private ?string $fixedId;

    private ?string $fixedTeacherId;

    public function __construct(?string $fixedId = null, ?string $fixedTeacherId = null)
    {
        $this->fixedId = $fixedId;
        $this->fixedTeacherId = $fixedTeacherId;
    }

    public function withId(string $id): self
    {
        return new self($id, $this->fixedTeacherId);
    }

    public function withTeacherId(string $teacherId): self
    {
        return new self($this->fixedId, $teacherId);
    }

    public function id(): ?string
    {
        return $this->fixedId ?? parent::id();
    }

    public function teacherId(): string
    {
        return $this->fixedTeacherId ?? parent::teacherId();
    }
}
