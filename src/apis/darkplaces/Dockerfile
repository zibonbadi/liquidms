FROM python:3
WORKDIR /usr/src/app

COPY ./src/apis/darkplaces .
RUN pip install --no-cache-dir -r requirements.txt

EXPOSE 27950

CMD ["python", "-u", "__main__.py"]
#CMD ["tail", "-f", "/dev/null"]
