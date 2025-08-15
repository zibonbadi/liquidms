FROM python:3
WORKDIR /usr/src/app

COPY ./src/apis/srb2legacy .
RUN pip install --no-cache-dir -r requirements.txt

EXPOSE 28900

CMD ["python", "-u", "__main__.py"]
#CMD ["tail", "-f", "/dev/null"]
