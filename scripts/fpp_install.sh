#!/bin/bash
set -x

sudo apt-get update && sudo apt-get install -y python3 python3-pip;
python3 -m pip install requests;
python3 -m pip install requests python-crontab;
