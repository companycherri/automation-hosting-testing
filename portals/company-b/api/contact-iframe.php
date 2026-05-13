<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Arial,sans-serif;padding:14px;background:#fff}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  label{display:block;font-size:11px;font-weight:700;color:#718096;margin-bottom:4px}
  input{width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px}
  input:focus{outline:none;border-color:#805ad5}
</style>
</head>
<body>
<div class="row">
  <div>
    <label for="contact-name">Contact Name</label>
    <input type="text" id="contact-name" name="contact_name" data-testid="iframe-contact-name"
           placeholder="e.g. Ahmad bin Ali">
  </div>
  <div>
    <label for="contact-phone">Phone Number</label>
    <input type="text" id="contact-phone" name="contact_phone" data-testid="iframe-contact-phone"
           placeholder="e.g. 012-3456789">
  </div>
</div>
</body>
</html>
