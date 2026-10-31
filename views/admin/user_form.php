<h2 class="h3 mb-3">Benutzer anlegen</h2>
<form method="post" action="<?= e(url('admin/users')) ?>" style="max-width:640px">
    <?= csrf_field() ?>
    <div class="card mb-3"><div class="card-body">
        <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= e($old['name']) ?>" required maxlength="120" autofocus></div>
        <div class="mb-3"><label class="form-label">E-Mail</label><input type="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required></div>
        <div class="mb-3"><label class="form-label">Rolle</label>
            <select name="role" class="form-select">
                <option value="user" <?= $old['role'] === 'user' ? 'selected' : '' ?>>Standard-User</option>
                <option value="admin" <?= $old['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
            </select></div>
        <fieldset class="mb-2"><legend class="form-label fs-6">Zugang</legend>
            <div class="form-check mb-2"><input class="form-check-input" type="radio" name="mode" id="m1" value="invite" <?= $old['mode'] === 'invite' ? 'checked' : '' ?>>
                <label class="form-check-label" for="m1">Einladungs-Link per E-Mail (7 Tage gültig) – Benutzer legt sein Passwort selbst fest</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="mode" id="m2" value="temp" <?= $old['mode'] === 'temp' ? 'checked' : '' ?>>
                <label class="form-check-label" for="m2">Temporäres Passwort erzeugen (wird einmalig angezeigt, muss beim ersten Login geändert werden)</label></div>
        </fieldset>
        <p class="small text-body-secondary mb-0">Bei der ersten Anmeldung richtet der Benutzer verpflichtend die Zwei-Faktor-Authentifizierung ein.</p>
    </div></div>
    <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Benutzer anlegen</button><a class="btn btn-outline-secondary" href="<?= e(url('admin/users')) ?>">Abbrechen</a></div>
</form>
