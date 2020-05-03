#!/bin/bash
set -x

which python;
python --version;
python3 --version;
sudo apt-get update && sudo apt-get install -y python3 python3-pip;
python3 -m pip install requests;
which python;
python --version;