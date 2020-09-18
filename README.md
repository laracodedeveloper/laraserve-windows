
## Introduction

Laraserve is a Laravel development environment for Windows. No Vagrant, no `/etc/hosts` file. You can even share your sites publicly using local tunnels. _Yeah, we like it too._

Laravel Laraserve configures your Windows to always run Nginx in the background when your machine starts. Then, using [Acrylic DNS](http://mayakron.altervista.org/wikibase/show.php?id=AcrylicHome), Laraserve proxies all requests on the `*.test` domain to point to sites installed on your local machine.

## Documentation

Before installation, make sure that no other programs such as Apache or Nginx are binding to your local machine's port 80. <br> Also make sure to open your preferred terminal (CMD, Git Bash, PowerShell, etc.) as Administrator. 

- If you don't have PHP installed, open PowerShell (3.0+) as Administrator and run: 

```bash
# PHP 7.4
Set-ExecutionPolicy RemoteSigned; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri "https://github.com/laracodedeveloper/laraserve-windows/raw/master/bin/php74.ps1" -OutFile $env:temp\php74.ps1; .$env:temp\php74.ps1

# PHP 7.3
Set-ExecutionPolicy RemoteSigned; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri "https://github.com/laracodedeveloper/laraserve-windows/raw/master/bin/php73.ps1" -OutFile $env:temp\php73.ps1; .$env:temp\php73.ps1
```

> This script will download and install PHP for you and add it to your environment path variable. PowerShell is only required for this step.

- If you don't have Composer installed, make sure to [install](https://getcomposer.org/Composer-Setup.exe) it.

- Install Laraserve with Composer via `composer global require laracodedeveloper/laraserve-windows`.

- Run the `laraserve install` command. This will configure and install Laraserve and register Laraserve's daemon to launch when your system starts.

- If you're installing on Windows 10, you may need to [manually configure](http://mayakron.altervista.org/wikibase/show.php?id=AcrylicWindows10Configuration) Windows to use the Acrylic DNS proxy.

Laraserve will automatically start its daemon each time your machine boots. There is no need to run `laraserve start` or `laraserve install` ever again once the initial Laraserve installation is complete.

## Known Issues

- When sharing sites the url will not be copied to the clipboard.
- You must run the `laraserve` commands from the drive where Laraserve is installed, except for park and link. See [#12](https://github.com/laracodedeveloper/laraserve-windows/issues/12#issuecomment-283111834).
- If your machine is not connected to the internet you'll have to manually add the domains in your `hosts` file or you can install the "Microsoft Loopback Adapter" as this simulates an active local network interface that Laraserve can bind too.

## Useful Links

- [Install ImageMagick](https://mlocati.github.io/articles/php-windows-imagick.html)

