package HW5.HW5;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileWriter;
import java.io.IOException;
import java.io.InputStream;

import org.apache.tika.exception.TikaException;
import org.apache.tika.metadata.Metadata;
import org.apache.tika.parser.AutoDetectParser;
import org.apache.tika.parser.ParseContext;
import org.apache.tika.sax.BodyContentHandler;
import org.xml.sax.SAXException;


public class BigParser {
	
	public static void main(String[] args) throws IOException, SAXException, TikaException {
		String url = "./BG/";
		FileWriter writer = new FileWriter("big.txt");
		/* Start reading HTML files. */
		File dir = new File(url);
		for(File file : dir.listFiles()) {
			if(!file.getName().contains(".html")) {
				continue;
			}
			
			parseToPlainText(file, writer);
		}
		
		writer.flush();
		writer.close();
	}
	
	public static void parseToPlainText(File file, FileWriter writer) throws IOException, SAXException, TikaException {
		
        
		InputStream input = new FileInputStream(file);
        BodyContentHandler textHandler = new BodyContentHandler(-1);
        AutoDetectParser parser = new AutoDetectParser();
        Metadata metadata = new Metadata();
        ParseContext context = new ParseContext();
        parser.parse(input, textHandler, metadata, context);
        
        String content =  textHandler.toString().trim();
        content = content.replaceAll("\\s", " "); // delete continuous spaces and newline.
        writer.append(content + "\n"); // write to local file.
        
        input.close();
	}

	

}
	
   

