# Original script from: https://github.com/namukcom/SynologyCloudFlareDDNS/blob/master/setddns.py
# Instructions for installation:
#   Installation - Simple way (requires DSM 7.0+ or Python3 installed)
#   1. Open Task Scheduler (Control Panel - [Services] Task Scheduler)
#   2. Create a user-defined script item. Create - Triggered Task - User-defined script
#      [General Tab]
#      Task: Cloudflare DDNS (not important)
#      User: root
#      Event: Boot-up
#      Pre-task: none
#      Enabled: Checked
#      
#      [Task Settings Tab]
#      [Run Command] User-defined script
#          curl https://raw.githubusercontent.com/aleclerc7/SynologyCloudflareDDNS/master/CloudflareSynologyDDNSProvider.py | python3 -
#   3. Press OK
#   4. Right-Click on the task you've just created.
#   5. Click Run
#   6. You can see Cloudflare DDNS has been added to your DDNS list.
#   7. Setup DDNS in Synology DSM (You can use "API Tokens" or "Global API Key")
#      See https://github.com/namukcom/SynologyCloudFlareDDNS for more details.

import configparser
import urllib.request
import os, stat

url = 'https://raw.githubusercontent.com/aleclerc7/SynologyCloudflareDDNS/master/cloudflare.php'
target_file = '/usr/syno/bin/ddns/cloudflare.php'

config= configparser.ConfigParser()
config.read('/etc.defaults/ddns_provider.conf')

try:
        config['Cloudflare']
except KeyError:
        config['Cloudflare']= {}

config['Cloudflare']['modulepath'] = '/usr/syno/bin/ddns/cloudflare.php'
config['Cloudflare']['queryurl'] = 'https://www.cloudflare.com/'

with open('/etc.defaults/ddns_provider.conf', 'w') as configfile:
        config.write(configfile)

urllib.request.urlretrieve(url, target_file)
os.chmod(target_file, stat.S_IRUSR |  stat.S_IWUSR |  stat.S_IXUSR |  stat.S_IRGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IXOTH)
