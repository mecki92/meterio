<?php
declare(strict_types=1);

/** Statische Seiten: Impressum, Datenschutz (öffentlich) und Versionsverlauf (angemeldet). */
final class PageController
{
    public function impressum(): void
    {
        view('pages/impressum', ['title' => 'Impressum', 'legal' => $this->legal()]);
    }

    public function privacy(): void
    {
        view('pages/privacy', ['title' => 'Datenschutzerklärung', 'legal' => $this->legal()]);
    }

    public function changelog(): void
    {
        Auth::require();
        view('pages/changelog', ['title' => 'Versionen', 'active' => '', 'releases' => require ROOT . '/src/changelog.php']);
    }

    private function legal(): array
    {
        $l = (array) Config::get('legal', []);
        foreach (['name', 'street', 'city', 'email', 'phone', 'host'] as $k) {
            $l[$k] = trim((string) ($l[$k] ?? ''));
        }
        return $l;
    }
}
