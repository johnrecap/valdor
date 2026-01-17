# دليل حل مشاكل Nginx 404 على aaPanel

## المشكلة

عند فتح الدومين `app1.saeeddev.com` يظهر خطأ **404 Not Found** من nginx رغم أن الملفات موجودة والإعدادات صحيحة.

## السبب الجذري

في aaPanel، عند وجود موقع آخر يستمع على `IP:80` بشكل صريح مثل:

```nginx
listen 212.47.65.222:80;
```

بينما الموقع الجديد يستمع على:

```nginx
listen 80;  # يعني 0.0.0.0:80
```

nginx يفضل الـ server block اللي بيستمع على الـ IP المحدد، فجميع الطلبات على الـ public IP تروح للموقع الخطأ.

## التشخيص

### 1. تأكد أن الموقع يعمل على localhost

```bash
curl -I -H "Host: app1.saeeddev.com" http://127.0.0.1/
```

لو رجع `200` أو `302` → الموقع شغال لكن المشكلة في الـ routing.

### 2. جرب على الـ Public IP

```bash
curl -I -H "Host: app1.saeeddev.com" http://212.47.65.222/
```

لو رجع `404` → المشكلة في ترتيب الـ server blocks.

### 3. شوف الـ listen directives في كل المواقع

```bash
grep -r "listen" /www/server/panel/vhost/nginx/*.conf
```

## الحل

### عدّل ملف الموقع ليستمع على الـ IP مباشرةً

```bash
# استبدل listen 80; بـ listen IP:80;
sed -i 's/listen 80;/listen 212.47.65.222:80;/' /www/server/panel/vhost/nginx/app1.saeeddev.com.conf

# أعد تحميل nginx
/www/server/nginx/sbin/nginx -s reload

# تأكد من التعديل
grep "listen" /www/server/panel/vhost/nginx/app1.saeeddev.com.conf
```

## ملاحظات مهمة

### 1. DNS Settings

تأكد أن الـ DNS موجه للـ IP الصحيح:

```bash
dig app1.saeeddev.com +short
# يجب أن يظهر: 212.47.65.222
```

### 2. APP_URL في Laravel

تأكد من تحديث `.env`:

```
APP_URL=http://app1.saeeddev.com
```

### 3. Storage Symlink

```bash
php artisan storage:link
```

### 4. Permissions

```bash
chmod -R 755 storage bootstrap/cache
chown -R www:www storage bootstrap/cache
```

## الأوامر المفيدة

```bash
# اختبار nginx config
/www/server/nginx/sbin/nginx -t

# إعادة تحميل nginx
/www/server/nginx/sbin/nginx -s reload

# شوف الـ nginx processes
ps aux | grep nginx

# شوف مين على port 80
ss -tlnp | grep :80

# شوف الـ error logs
tail -50 /www/wwwlogs/app1.saeeddev.com.error.log
tail -50 /www/wwwlogs/nginx_error.log
```

## ملخص سريع للحل

1. ✅ تأكد من DNS
2. ✅ تأكد من APP_URL في .env
3. ✅ عدّل listen directive ليكون `listen IP:80;`
4. ✅ أعد تحميل nginx

---

## مشكلة SSL / HTTPS

### المشكلة

عند تفعيل SSL، الموقع يحول لموقع تاني أو الشهادة مش صحيحة.

### السبب

نفس مشكلة HTTP - لازم nginx يسمع على `IP:443` مش `443` بس.

### الحل

```bash
# عدّل الـ config
sed -i 's/listen 443/listen 212.47.65.222:443/g' /www/server/panel/vhost/nginx/app1.saeeddev.com.conf

# احذف quic لو موجود (مش مدعوم في بعض النسخ)
sed -i '/quic/d' /www/server/panel/vhost/nginx/app1.saeeddev.com.conf

# اختبر وأعد تحميل
/www/server/nginx/sbin/nginx -t
/www/server/nginx/sbin/nginx -s reload
```

### تأكد من الشهادة

```bash
curl -Ik https://app1.saeeddev.com
```

### لو الشهادة غلط

من **aaPanel** → **Website** → **SSL** → أعد تطبيق **Let's Encrypt**
