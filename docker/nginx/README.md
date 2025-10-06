# Nginx Configuration

This directory contains the nginx configuration files for the Service Booking application.

## Files

- `nginx.conf` - Main nginx configuration
- `conf.d/app.conf` - Application-specific configuration
- `.htpasswd` - Basic authentication credentials for MailHog
- `.htpasswd.example` - Example basic auth file

## MailHog Basic Authentication

MailHog is protected with HTTP Basic Authentication. The default credentials are:

- **Username**: `admin`
- **Password**: `mailhog123`

### Changing the Password

To change the MailHog password:

1. Generate a new password hash:
   ```bash
   docker run --rm httpd:alpine htpasswd -nb admin your_new_password
   ```

2. Update the `.htpasswd` file with the generated output

3. Restart nginx:
   ```bash
   docker-compose restart nginx
   ```

### First Time Setup

If the `.htpasswd` file doesn't exist, copy from the example:

```bash
cp docker/nginx/.htpasswd.example docker/nginx/.htpasswd
```

## Port Configuration

The nginx configuration includes:

- `port_in_redirect off` - Prevents nginx from adding/removing ports in redirects
- `absolute_redirect off` - Uses relative redirects to preserve the original host and port
