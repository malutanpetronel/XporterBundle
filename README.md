# Aquis Xporter Bundle

---
## INSTALATION
To correctly install the bundle please follow the next steps

---
#### Install the bundle
<pre>
composer require aquis/xporter-plugin:*@dev
</pre>

#### Enable the bundle
In your project config/bundles.php verify and eventually add:
<pre>
Aquis\XporterBundle\XporterBundle::class => ['all' => true],
</pre>
