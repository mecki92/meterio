<h2>Benutzer anlegen</h2>
<form method="post" action="<?= e(url('admin/users')) ?>">
    <?= csrf_field() ?>
    <article>
        <label>Name <input name="name" value="<?= e($old['name']) ?>" required maxlength="120" autofocus></label>
        <label>E-Mail <input type="email" name="email" value="<?= e($old['email']) ?>" required></label>
        <label>Rolle
            <select name="role">
                <option value="user" <?= $old['role'] === 'user' ? 'selected' : '' ?>>Standard-User</option>
                <option value="admin" <?= $old['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
            </select>
        </label>
        <fieldset>
            <legend>Zugang</legend>
            <label><input type="radio" name="mode" value="invite" <?= $old['mode'] === 'invite' ? 'checked' : '' ?>> Einladungs-Link per E-Mail (7 Tage gültig) – Benutzer legt sein Passwort selbst fest</label>
            <label><input type="radio" name="mode" value="temp" <?= $old['mode'] === 'temp' ? 'checked' : '' ?>> Temporäres Passwort erzeugen (wird einmalig angezeigt, muss beim ersten Login geändert werden)</label>
        </fieldset>
        <p class="small muted">Bei der ersten Anmeldung richtet der Benutzer verpflichtend die Zwei-Faktor-Authentifizierung ein.</p>
    </article>
    <div class="actions"><button type="submit">Benutzer anlegen</button><a role="button" class="secondary outline" href="<?= e(url('admin/users')) ?>">Abbrechen</a></div>
</form>
